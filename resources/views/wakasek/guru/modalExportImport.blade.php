<div id="exportImportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-lg font-medium text-gray-900">Export/Import Data Guru</h3>
                <button type="button" id="closeExportImport" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="mt-4">
                <div class="flex border-b border-gray-200 mb-4">
                    <button type="button" id="exportTab"
                        class="tab-button px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600">
                        Export Data
                    </button>
                    <button type="button" id="importTab" @unless (auth()->user()->isSuperAdmin()) style="display:none" @endunless
                        class="tab-button px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                        Import Data
                    </button>
                </div>

                <!-- Export -->
                <div id="exportContent" class="tab-content">
                    <div class="space-y-3">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Pilih format export:</h4>

                        <button type="button" onclick="window.location='{{ route('guru.export.excel') }}'"
                            class="w-full flex items-center justify-center px-4 py-3 border border-green-300 rounded-md bg-green-50 hover:bg-green-100 text-green-700 transition-colors">
                            <i class="bi bi-file-earmark-excel mr-2"></i>
                            Export ke Excel (.xlsx)
                        </button>

                        <button type="button" onclick="window.location='{{ route('guru.export.pdf') }}'"
                            class="w-full flex items-center justify-center px-4 py-3 border border-red-300 rounded-md bg-red-50 hover:bg-red-100 text-red-700 transition-colors">
                            <i class="bi bi-file-earmark-pdf mr-2"></i>
                            Export ke PDF (.pdf)
                        </button>
                    </div>
                </div>

                <!-- Import -->
                <div id="importContent" class="tab-content hidden">
                    <form id="importForm" action="{{ route('guru.import') }}" method="POST"
                        enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Upload file untuk import data:</h4>

                            <div id="dropZoneGuru"
                                class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer transition-colors hover:border-gray-400">
                                <input type="file" name="file" id="importFileGuru" class="hidden" accept=".xlsx,.xls,.csv">
                                <i class="bi bi-cloud-arrow-up text-4xl text-gray-400"></i>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium text-blue-600">Klik area ini</span>
                                        atau drag &amp; drop file
                                    </p>
                                    <p class="text-xs text-gray-500">Excel (MAX. 10MB)</p>
                                </div>
                            </div>

                            <div id="selectedFile" class="hidden mt-3 p-3 bg-gray-50 rounded-md">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <i class="bi bi-file-earmark-excel text-2xl text-green-500"></i>
                                        <div class="ml-3">
                                            <p id="fileName" class="text-sm font-medium text-gray-900"></p>
                                            <p id="fileSize" class="text-xs text-gray-500"></p>
                                        </div>
                                    </div>
                                    <button type="button" onclick="removeFile()" class="text-red-400 hover:text-red-600">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t">
                            <a href="{{ route('guru.template') }}"
                                class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                Download Template Excel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end space-x-3 pt-4 border-t mt-6">
                <button type="button" id="cancelBtn"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit" form="importForm" id="processBtn"
                    class="hidden px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                    Proses Import
                </button>
            </div>
        </div>
    </div>
</div>