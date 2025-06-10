import React, { useEffect, useState } from 'react';
// added by BZ: Collapsible log section types
const LogSectionNames = ["ERROR", "WARN", "INFO", "OTHER"] as const;
type LogSection = typeof LogSectionNames[number];

import { ServerContext } from '@/state/server';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import CopyOnClick from '@/components/elements/CopyOnClick';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import tw from 'twin.macro';
import axios from 'axios';

interface McLogEntry {
    id: string;
    url: string;
    uploadedAt: string; // ISO string for the upload timestamp
}

interface InsightsData {
    version: string;
    name: string;
    analysis: {
        problems: Array<{
            message: string;
            solutions?: Array<{ message: string }>;
        }>;
    };
}

const LogsPage: React.FC = () => {
    // added by BZ: Collapsed state for log sections
    const [collapsed, setCollapsed] = useState<Record<LogSection, boolean>>({
        ERROR: false,
        WARN: false,
        INFO: true,
        OTHER: true,
    });
    // added by BZ: Toggle for grouped/collapsibled
    const [showOriginal, setShowOriginal] = useState(false);


    const [logs, setLogs] = useState<string[]>([]);
    const [mclogsUrls, setMclogsUrls] = useState<McLogEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedLogData, setSelectedLogData] = useState<string | null>(null);
    const [insightsData, setInsightsData] = useState<InsightsData | null>(null);
    const [historyVisible, setHistoryVisible] = useState(false);
    const [showModal, setShowModal] = useState(false);

    const [currentLogsPage, setCurrentLogsPage] = useState<number>(1);
    const [currentHistoryPage, setCurrentHistoryPage] = useState<number>(1);
    const logsPerPage = 5; 
    const maxPageButtons = 5; 

    const [sortOrder, setSortOrder] = useState<'newest' | 'oldest'>('newest'); // Sorting order for MCLogs
    const [logSortOrder, setLogSortOrder] = useState<'newest' | 'oldest'>('newest'); // Sorting order for logs

    const { uuid } = ServerContext.useStoreState((state) => state.server.data!);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const clearFlashes = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes.clearFlashes);
    const addError = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes.addError);

    // Apply sorting to logs
    const sortedLogs = React.useMemo(() => {
        let sorted = [...logs];
        // Example sorting: If 'newest', we reverse the alphabetical order:
        // Adjust this logic if you have a better date-based pattern.
        sorted.sort();
        if (logSortOrder === 'newest') {
            sorted.reverse();
        }
        return sorted;
    }, [logs, logSortOrder]);

    const paginatedLogs = sortedLogs.slice((currentLogsPage - 1) * logsPerPage, currentLogsPage * logsPerPage);
    const paginatedHistory = mclogsUrls.slice((currentHistoryPage - 1) * logsPerPage, currentHistoryPage * logsPerPage);

    const totalLogsPages = Math.ceil(sortedLogs.length / logsPerPage);
    const totalHistoryPages = Math.ceil(mclogsUrls.length / logsPerPage);

    const handleLogsPageChange = (pageNumber: number) => {
        setCurrentLogsPage(pageNumber);
    };

    const handleHistoryPageChange = (pageNumber: number) => {
        setCurrentHistoryPage(pageNumber);
    };

    const saveToLocalStorage = (data: McLogEntry) => {
        const storedData = JSON.parse(localStorage.getItem(`${uuid}_mclogs`) || '[]');
        storedData.push(data);
        localStorage.setItem(`${uuid}_mclogs`, JSON.stringify(storedData));
    };

    const loadFromLocalStorage = () => {
        const storedData: McLogEntry[] = JSON.parse(localStorage.getItem(`${uuid}_mclogs`) || '[]');
        setMclogsUrls(storedData);
    };

    const removeFromLocalStorage = (id: string) => {
        const storedData: McLogEntry[] = JSON.parse(localStorage.getItem(`${uuid}_mclogs`) || '[]');
        const updatedData = storedData.filter((entry) => entry.id !== id);
        localStorage.setItem(`${uuid}_mclogs`, JSON.stringify(updatedData));
        setMclogsUrls(updatedData);
    };

    const clearAllLogs = () => {
        localStorage.removeItem(`${uuid}_mclogs`);
        setMclogsUrls([]);
        setShowModal(false);
    };

    const fetchLogs = async () => {
        clearFlashes('logs');
        setLoading(true);
        try {
            const response = await axios.get(`/api/client/servers/${uuid}/files/list?directory=/logs`, {
                headers: { 'X-CSRF-TOKEN': csrfToken ?? '' },
            });

            const files = response.data.data.map((file: { attributes: { name: string } }) => file.attributes.name);
            setLogs(files);
        } catch (error) {
            console.error('Error fetching logs:', error);
            addError({ key: 'logs', message: 'Failed to fetch logs. Please try again later.' });
        } finally {
            setLoading(false);
        }
    };

    const handleUploadToMclogs = async (fileName: string) => {
        clearFlashes('logs');
        setLoading(true);

        try {
            let logData: string;

            if (fileName.endsWith('.gz')) {
                // Decompress the file using the API
                const decompressResponse = await axios.post(
                    `/api/client/servers/${uuid}/files/decompress`,
                    { root: '/logs', file: fileName },
                    { headers: { 'X-CSRF-TOKEN': csrfToken ?? '' } }
                );

                if (decompressResponse.status === 204) {
                    const decompressedFileName = fileName.replace('.gz', '');

                    // Fetch the decompressed file content
                    const fileContentResponse = await axios.get(
                        `/api/client/servers/${uuid}/files/contents?file=/logs/${decompressedFileName}`,
                        { headers: { 'X-CSRF-TOKEN': csrfToken ?? '' } }
                    );

                    logData = fileContentResponse.data;

                    // Optionally, delete the decompressed file after use
                    await axios.post(
                        `/api/client/servers/${uuid}/files/delete`,
                        { root: '/logs', files: [decompressedFileName] },
                        { headers: { 'X-CSRF-TOKEN': csrfToken ?? '' } }
                    );
                } else {
                    throw new Error('Failed to decompress the file.');
                }
            } else {
                // Fetch the raw file content if it's not compressed
                const fileContentResponse = await axios.get(
                    `/api/client/servers/${uuid}/files/contents?file=/logs/${fileName}`,
                    { headers: { 'X-CSRF-TOKEN': csrfToken ?? '' } }
                );

                logData = fileContentResponse.data;
            }

            // Upload the log data to MCLogs
            const formData = new URLSearchParams();
            formData.append('content', `// Log file: ${fileName}\n\n${logData}`);

            const uploadResponse = await axios.post('https://api.mclo.gs/1/log', formData, {
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            });

            if (uploadResponse.data.success && uploadResponse.data.url) {
                const newLog = {
                    id: uploadResponse.data.id,
                    url: uploadResponse.data.url,
                    uploadedAt: new Date().toISOString(),
                };
                setMclogsUrls((prev) => [newLog, ...prev]);
                saveToLocalStorage(newLog);

                await fetchMclogsData(uploadResponse.data.id);
            } else {
                addError({ key: 'logs', message: 'Failed to upload logs to MCLogs.' });
            }
        } catch (error) {
            console.error('Error uploading logs:', error);
            addError({ key: 'logs', message: 'An error occurred while uploading logs. Please try again later.' });
        } finally {
            setLoading(false);
        }
    };

    const getServerImageUrl = (serverName: string) => {
        const nameToImageMapping: Record<string, string> = {
            Arclight: '/extensions/mclogs/versions/arclight.png',
            BungeeCord: '/extensions/mclogs/versions/bungeecord.png',
            Canvas: '/extensions/mclogs/versions/canvas.png',
            Fabric: '/extensions/mclogs/versions/fabric.png',
            Folia: '/extensions/mclogs/versions/folia.png',
            Forge: '/extensions/mclogs/versions/forge.png',
            Leaves: '/extensions/mclogs/versions/leaves.png',
            Mohist: '/extensions/mclogs/versions/mohist.png',
            NeoForge: '/extensions/mclogs/versions/neoforge.png',
            Paper: '/extensions/mclogs/versions/paper.png',
            Pufferfish: '/extensions/mclogs/versions/pufferfish.png',
            Purpur: '/extensions/mclogs/versions/purpur.png',
            Quilt: '/extensions/mclogs/versions/quilt.png',
            Sponge: '/extensions/mclogs/versions/sponge.png',
            Vanilla: '/extensions/mclogs/versions/vanilla.png',
            Velocity: '/extensions/mclogs/versions/velocity.png',
            Waterfall: '/extensions/mclogs/versions/waterfall.png',
        };
        return nameToImageMapping[serverName] || '/extensions/mclogs/versions/vanilla.png';
    };

    const fetchMclogsData = async (id: string) => {
        clearFlashes('logs');
        setLoading(true);
        try {
            const rawResponse = await axios.get(`https://api.mclo.gs/1/raw/${id}`);
            const insightsResponse = await axios.get(`https://api.mclo.gs/1/insights/${id}`);

            setSelectedLogData(rawResponse.data);
            setInsightsData(insightsResponse.data);
        } catch (error) {
            console.error('Error fetching MCLogs data:', error);
            addError({ key: 'logs', message: 'Failed to fetch MCLogs data. Please try again later.' });
        } finally {
            setLoading(false);
        }
    };

    // Load from local storage and fetch logs on mount
    useEffect(() => {
        fetchLogs();
        loadFromLocalStorage();
    }, []);

    // Re-sort mclogsUrls when sortOrder changes
    useEffect(() => {
        setMclogsUrls((prev) => {
            const sorted = [...prev].sort((a, b) => {
                const aTime = new Date(a.uploadedAt).getTime();
                const bTime = new Date(b.uploadedAt).getTime();
                return sortOrder === 'newest' ? bTime - aTime : aTime - bTime;
            });
            return sorted;
        });
    }, [sortOrder]);

    const renderPaginationControls = (
        currentPage: number,
        totalPages: number,
        onPageChange: (page: number) => void
    ) => {
        if (totalPages <= 1) return null;

        let startPage = Math.max(1, currentPage - Math.floor(maxPageButtons / 2));
        let endPage = startPage + maxPageButtons - 1;

        if (endPage > totalPages) {
            endPage = totalPages;
            startPage = Math.max(1, endPage - maxPageButtons + 1);
        }

        const pageNumbers = [];
        for (let i = startPage; i <= endPage; i++) {
            pageNumbers.push(i);
        }

        return (
            <div css={tw`flex justify-center items-center mt-4 space-x-1`}>
                {currentPage > 1 && (
                    <>
                        <button
                            css={tw`px-3 py-1 rounded bg-gray-500 text-white hover:bg-gray-600`}
                            onClick={() => onPageChange(1)}
                        >
                            First
                        </button>
                        <button
                            css={tw`px-3 py-1 rounded bg-gray-500 text-white hover:bg-gray-600`}
                            onClick={() => onPageChange(currentPage - 1)}
                        >
                            Prev
                        </button>
                    </>
                )}
                {pageNumbers.map((page) => (
                    <button
                        key={page}
                        css={[
                            tw`px-3 py-1 rounded bg-gray-500 text-white hover:bg-gray-600`,
                            currentPage === page && tw`bg-blue-500`,
                        ]}
                        onClick={() => onPageChange(page)}
                    >
                        {page}
                    </button>
                ))}
                {currentPage < totalPages && (
                    <>
                        <button
                            css={tw`px-3 py-1 rounded bg-gray-500 text-white hover:bg-gray-600`}
                            onClick={() => onPageChange(currentPage + 1)}
                        >
                            Next
                        </button>
                        <button
                            css={tw`px-3 py-1 rounded bg-gray-500 text-white hover:bg-gray-600`}
                            onClick={() => onPageChange(totalPages)}
                        >
                            Last
                        </button>
                    </>
                )}
            </div>
        );
    };

    if (loading) {
        return <Spinner size={'large'} centered />;
    }

    return (
        <ServerContentBlock title={'Logs'}>
            <FlashMessageRender byKey={'logs'} css={tw`mb-4`} />
            <div css={tw`p-6 bg-gray-600 rounded`} className="ContentBox___StyledDiv-sc-mjlt6f-2 iGOcRf">
                <div css={tw`flex items-center justify-between mb-2`}>
                    <h3 css={tw`text-lg text-neutral-100`}>Available Logs</h3>
                    <select
                        css={tw`text-sm bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600`}
                        value={logSortOrder}
                        onChange={(e) => setLogSortOrder(e.target.value as 'newest' | 'oldest')}
                    >
                        <option value="newest">Newest to Oldest</option>
                        <option value="oldest">Oldest to Newest</option>
                    </select>
                </div>
                {!sortedLogs.length ? (
                    <p css={tw`text-sm text-neutral-300`}>
                        No logs found in the server directory. Are you running a Minecraft Server?
                    </p>
                ) : (
                    <>
                        <div css={tw`divide-y divide-white`}>
                            {paginatedLogs.map((logFile) => (
                                <div key={logFile} css={tw`flex items-center justify-between py-2`}>
                                    <span css={tw`text-sm text-neutral-200`}>{logFile}</span>
                                    <button
                                        css={tw`text-sm bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600`}
                                        onClick={() => handleUploadToMclogs(logFile)}
                                    >
                                        Upload to MCLogs
                                    </button>
                                </div>
                            ))}
                        </div>
                        {renderPaginationControls(currentLogsPage, totalLogsPages, handleLogsPageChange)}
                    </>
                )}
            </div>

            <div css={tw`mt-6`}>
                <button
                    css={tw`text-sm bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600`}
                    onClick={() => setHistoryVisible(!historyVisible)}
                >
                    {historyVisible ? 'Hide MCLogs History' : 'Show MCLogs History'}
                </button>
                {historyVisible && (
                    <div css={tw`p-6 bg-gray-600 rounded mt-4`} className="ContentBox___StyledDiv-sc-mjlt6f-2 iGOcRf">
                        <div css={tw`flex items-center justify-between mb-4`}>
                            <h3 css={tw`text-lg text-neutral-100`}>MCLogs History</h3>
                            <div css={tw`flex items-center space-x-4`}>
                                <button
                                    css={tw`text-sm bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600`}
                                    onClick={() => setShowModal(true)}
                                >
                                    Delete All History
                                </button>
                                {/* Sort Order Dropdown */}
                                <select
                                    css={tw`text-sm bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600`}
                                    value={sortOrder}
                                    onChange={(e) => setSortOrder(e.target.value as 'newest' | 'oldest')}
                                >
                                    <option value="newest">Newest to Oldest</option>
                                    <option value="oldest">Oldest to Newest</option>
                                </select>
                            </div>
                        </div>
                        {!mclogsUrls.length ? (
                            <p css={tw`text-sm text-neutral-300`}>No MCLogs uploads found.</p>
                        ) : (
                            <>
                                <ul css={tw`list-none p-0`}>
                                    {paginatedHistory.map(({ id, url, uploadedAt }) => (
                                        <li key={id} css={tw`py-2 flex justify-between items-center`}>
                                            <div>
                                                <a
                                                    href={url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    css={tw`text-sm text-white hover:underline`}
                                                >
                                                    {url}
                                                </a>
                                                <span css={tw`block text-sm text-neutral-400`}>
                                                    Uploaded on: {new Date(uploadedAt).toLocaleString()}
                                                </span>
                                            </div>
                                            <div css={tw`flex space-x-2`}>
                                                <CopyOnClick text={url}>
                                                    <button
                                                        css={tw`text-sm bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600`}
                                                    >
                                                        <i className="fas fa-link"></i>
                                                    </button>
                                                </CopyOnClick>
                                                <button
                                                    css={tw`text-sm bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600`}
                                                    onClick={() => fetchMclogsData(id)}
                                                >
                                                    View Data
                                                </button>
                                                <button
                                                    css={tw`text-sm bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600`}
                                                    onClick={() => removeFromLocalStorage(id)}
                                                >
                                                    <i className="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                {renderPaginationControls(currentHistoryPage, totalHistoryPages, handleHistoryPageChange)}
                            </>
                        )}
                    </div>
                )}
            </div>

            {showModal && (
                <div css={tw`fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center`}>
                    <div css={tw`bg-gray-700 p-6 rounded text-center`}>
                        <h3 css={tw`text-lg text-white mb-4`}>Are you sure you want to clear all history?</h3>
                        <button
                            css={tw`text-sm bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 mr-4`}
                            onClick={clearAllLogs}
                        >
                            Yes, Delete All
                        </button>
                        <button
                            css={tw`text-sm bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600`}
                            onClick={() => setShowModal(false)}
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}

            {insightsData && (
                <div css={tw`p-6 bg-gray-600 rounded mt-6`} className="ContentBox___StyledDiv-sc-mjlt6f-2 iGOcRf">
                    <div css={tw`flex items-center mb-4`}>
                        <img
                            src={getServerImageUrl(insightsData.name || 'Vanilla')}
                            alt={insightsData.name || 'Unknown'}
                            css={tw`w-16 h-16 mr-4 rounded-full`}
                        />
                        <div>
                            <h3 css={tw`text-lg text-neutral-100`}>{insightsData.name || 'Unknown Server'}</h3>
                            <p css={tw`block text-sm text-neutral-400`}>Version: {insightsData.version || 'Not Available'}</p>
                        </div>
                    </div>

                    {insightsData.analysis?.problems?.length ? (
                        <div css={tw`bg-gray-800 p-4 rounded`}>
                            <h4 css={tw`text-base text-neutral-100 mb-2`}>Analysis</h4>
                            {insightsData.analysis.problems.map((problem, index) => (
                                <div key={index} css={tw`mb-4`}>
                                    <p css={tw`text-sm text-neutral-300 mb-1`}>{problem.message || 'No problem message available.'}</p>
                                    {problem.solutions?.length ? (
                                        <ul css={tw`list-disc list-inside text-sm text-neutral-400`}>
                                            {problem.solutions.map((solution, idx) => (
                                                <li key={idx}>{solution.message || 'No solution message available.'}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p css={tw`text-sm text-neutral-400`}>No solutions available.</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p css={tw`text-sm text-neutral-300`}>No problems found in analysis.</p>
                    )}
                </div>
            )}

            {selectedLogData && (
                <div css={tw`mt-6 p-6 bg-gray-700 rounded`} className="ContentBox___StyledDiv-sc-mjlt6f-2 iGOcRf">
                    <div css={tw`flex justify-between items-center mb-4`}>
                        <h3 css={tw`text-lg text-neutral-100`}>Selected Log Data</h3>
                        <button
                            css={tw`text-sm bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600`}
                            onClick={() => {
                                setSelectedLogData(null);
                                setInsightsData(null);
                            }}
                        >
                            Close
                        </button>
                    </div>
                    <h4 css={tw`text-base text-white mb-2`}>Raw Log</h4>
                    {/* added by BZ: Toggle for grouped/collapsibled */}
                    <button
                        css={tw`mb-4 px-2 py-1 rounded bg-neutral-800 text-neutral-200 text-xs hover:bg-neutral-700`}
                        onClick={() => setShowOriginal(v => !v)}
                        type="button"
                    >
                        {showOriginal ? 'Show Grouped/Collapsible View' : 'Show Original Log Order'}
                    </button>
                    <div css={tw`text-sm whitespace-pre-wrap mb-4`}>
                        {showOriginal ? (
                            // Original log order, color-coded only
                            selectedLogData.split('\n').map((line, index) => {
                                let lineStyle = tw`text-white`;
                                if (line.includes('WARN')) lineStyle = tw`text-[#FF8C00]`;
                                else if (line.includes('INFO')) lineStyle = tw`text-[#FFFF99]`;
                                else if (line.includes('ERROR')) lineStyle = tw`text-[#F62451]`;
                                return (
                                    <p css={lineStyle} key={index}>{line}</p>
                                );
                            })
                        ) : (
                            // Grouped/collapsibled view
                            (() => {
                                const grouped: Record<LogSection, string[]> = {
                                    ERROR: [],
                                    WARN: [],
                                    INFO: [],
                                    OTHER: [],
                                };
                                selectedLogData.split('\n').forEach(line => {
                                    if (line.includes('ERROR')) grouped.ERROR.push(line);
                                    else if (line.includes('WARN')) grouped.WARN.push(line);
                                    else if (line.includes('INFO')) grouped.INFO.push(line);
                                    else grouped.OTHER.push(line);
                                });
                                const lineStyle: Record<LogSection, any> = {
                                    ERROR: tw`text-[#F62451]`,
                                    WARN: tw`text-[#FF8C00]`,
                                    INFO: tw`text-[#FFFF99]`,
                                    OTHER: tw`text-white`,
                                };
                                return LogSectionNames.map(type => (
                                    grouped[type].length > 0 && (
                                        <div key={type} css={tw`mb-2`}>
                                            <button
                                                css={tw`mb-1 px-2 py-1 rounded bg-neutral-800 text-neutral-200 text-xs hover:bg-neutral-700`}
                                                onClick={() => setCollapsed(c => ({ ...c, [type]: !c[type] }))}
                                                type="button"
                                            >
                                                {collapsed[type] ? `Show` : `Hide`} {grouped[type].length} {type} line{grouped[type].length !== 1 ? 's' : ''}
                                            </button>
                                            {!collapsed[type] && (
                                                <div>
                                                    {grouped[type].map((line, idx) => (
                                                        <p css={lineStyle[type]} key={idx}>
                                                            {line}
                                                        </p>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )
                                ));
                            })()
                        )}
                    </div>
                </div>
            )}
        </ServerContentBlock>
    );
};

export default LogsPage;
