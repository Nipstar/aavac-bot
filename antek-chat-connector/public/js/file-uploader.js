/**
 * File Uploader Component
 *
 * Handles multimodal file uploads with drag-and-drop, preview, and progress tracking
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

class AntekFileUploader {
    /**
     * Constructor
     *
     * @param {Object} config - Configuration object
     * @param {string} config.restUrl - WordPress REST API base URL
     * @param {string} config.nonce - WordPress nonce for authentication
     * @param {HTMLElement} config.dropZone - Drop zone element
     * @param {HTMLElement} config.fileInput - File input element
     * @param {Function} config.onUploadComplete - Callback when upload completes
     * @param {Function} config.onUploadError - Callback when upload fails
     * @param {Object} config.settings - Upload settings (max size, allowed types)
     */
    constructor(config) {
        this.restUrl = config.restUrl;
        this.nonce = config.nonce;
        this.dropZone = config.dropZone;
        this.fileInput = config.fileInput;
        this.onUploadComplete = config.onUploadComplete || (() => {});
        this.onUploadError = config.onUploadError || (() => {});
        this.settings = config.settings || {
            maxFileSizeMB: 50,
            allowedTypes: ['image', 'audio', 'document', 'video']
        };

        this.currentUploads = new Map(); // Track ongoing uploads
        this.previewContainer = null;

        this.init();
    }

    /**
     * Initialize uploader
     */
    init() {
        this.setupDropZone();
        this.setupFileInput();
        this.createPreviewContainer();
    }

    /**
     * Setup drag-and-drop zone
     */
    setupDropZone() {
        if (!this.dropZone) return;

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        // Add visual feedback
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.add('antek-drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.remove('antek-drag-over');
            }, false);
        });

        // Handle drop
        this.dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            this.handleFiles(files);
        }, false);
    }

    /**
     * Setup file input
     */
    setupFileInput() {
        if (!this.fileInput) return;

        this.fileInput.addEventListener('change', (e) => {
            const files = e.target.files;
            this.handleFiles(files);
            // Reset input so same file can be selected again
            e.target.value = '';
        });
    }

    /**
     * Create preview container
     */
    createPreviewContainer() {
        this.previewContainer = document.createElement('div');
        this.previewContainer.className = 'antek-file-preview-container';

        if (this.dropZone) {
            this.dropZone.parentNode.insertBefore(this.previewContainer, this.dropZone);
        }
    }

    /**
     * Handle file selection
     *
     * @param {FileList} files - Selected files
     */
    handleFiles(files) {
        Array.from(files).forEach(file => {
            // Validate file
            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showError(validation.error);
                return;
            }

            // Show preview
            this.showPreview(file);

            // Upload file
            this.uploadFile(file);
        });
    }

    /**
     * Validate file
     *
     * @param {File} file - File to validate
     * @return {Object} Validation result
     */
    validateFile(file) {
        // Check file size
        const maxSize = this.settings.maxFileSizeMB * 1024 * 1024;
        if (file.size > maxSize) {
            return {
                valid: false,
                error: `File size exceeds ${this.settings.maxFileSizeMB}MB limit`
            };
        }

        // Check file type
        const fileType = this.getFileType(file);
        if (!this.settings.allowedTypes.includes(fileType)) {
            return {
                valid: false,
                error: `File type ${fileType} is not allowed`
            };
        }

        return { valid: true };
    }

    /**
     * Get file type category
     *
     * @param {File} file - File object
     * @return {string} File type category
     */
    getFileType(file) {
        const mimeType = file.type;

        if (mimeType.startsWith('image/')) return 'image';
        if (mimeType.startsWith('audio/')) return 'audio';
        if (mimeType.startsWith('video/')) return 'video';
        if (mimeType.includes('pdf') || mimeType.includes('document')) return 'document';

        return 'unknown';
    }

    /**
     * Show file preview
     *
     * @param {File} file - File to preview
     */
    showPreview(file) {
        const fileId = this.generateFileId(file);
        const fileType = this.getFileType(file);

        const previewItem = document.createElement('div');
        previewItem.className = 'antek-file-preview-item';
        previewItem.dataset.fileId = fileId;

        // Preview content
        const previewContent = document.createElement('div');
        previewContent.className = 'antek-file-preview-content';

        // File icon or image thumbnail
        if (fileType === 'image') {
            const img = document.createElement('img');
            img.className = 'antek-file-thumbnail';
            const reader = new FileReader();
            reader.onload = (e) => {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
            previewContent.appendChild(img);
        } else {
            const icon = this.getFileIcon(fileType);
            previewContent.innerHTML = icon;
        }

        // File info
        const fileInfo = document.createElement('div');
        fileInfo.className = 'antek-file-info';
        fileInfo.innerHTML = `
            <div class="antek-file-name">${this.escapeHtml(file.name)}</div>
            <div class="antek-file-size">${this.formatFileSize(file.size)}</div>
        `;

        // Progress bar
        const progressBar = document.createElement('div');
        progressBar.className = 'antek-upload-progress';
        progressBar.innerHTML = '<div class="antek-upload-progress-bar"></div>';

        // Cancel button
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'antek-file-cancel';
        cancelBtn.innerHTML = '&times;';
        cancelBtn.onclick = () => this.cancelUpload(fileId);

        previewItem.appendChild(previewContent);
        previewItem.appendChild(fileInfo);
        previewItem.appendChild(progressBar);
        previewItem.appendChild(cancelBtn);

        this.previewContainer.appendChild(previewItem);
    }

    /**
     * Upload file to server
     *
     * @param {File} file - File to upload
     */
    async uploadFile(file) {
        const fileId = this.generateFileId(file);

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        this.currentUploads.set(fileId, xhr);

        // Progress tracking
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                this.updateProgress(fileId, percentComplete);
            }
        });

        // Upload complete
        xhr.addEventListener('load', () => {
            this.currentUploads.delete(fileId);

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    this.handleUploadSuccess(fileId, file, response);
                } catch (error) {
                    this.handleUploadError(fileId, 'Invalid server response');
                }
            } else {
                let errorMessage = 'Upload failed';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    errorMessage = errorResponse.message || errorMessage;
                } catch (e) {
                    // Use default error message
                }
                this.handleUploadError(fileId, errorMessage);
            }
        });

        // Upload error
        xhr.addEventListener('error', () => {
            this.currentUploads.delete(fileId);
            this.handleUploadError(fileId, 'Network error during upload');
        });

        // Upload aborted
        xhr.addEventListener('abort', () => {
            this.currentUploads.delete(fileId);
            this.removePreview(fileId);
        });

        // Send request
        xhr.open('POST', `${this.restUrl}/antek-chat/v1/upload`, true);
        xhr.setRequestHeader('X-WP-Nonce', this.nonce);
        xhr.send(formData);
    }

    /**
     * Handle successful upload
     *
     * @param {string} fileId - File identifier
     * @param {File} file - Original file object
     * @param {Object} response - Server response
     */
    handleUploadSuccess(fileId, file, response) {
        const previewItem = this.previewContainer.querySelector(`[data-file-id="${fileId}"]`);
        if (previewItem) {
            previewItem.classList.add('antek-upload-complete');

            // Remove progress bar
            const progressBar = previewItem.querySelector('.antek-upload-progress');
            if (progressBar) progressBar.remove();

            // Update cancel button to remove button
            const cancelBtn = previewItem.querySelector('.antek-file-cancel');
            if (cancelBtn) {
                cancelBtn.onclick = () => this.removePreview(fileId);
            }
        }

        // Call completion callback
        this.onUploadComplete({
            fileId: fileId,
            fileName: file.name,
            fileType: this.getFileType(file),
            mediaId: response.id,
            url: response.url,
            originalFile: file
        });
    }

    /**
     * Handle upload error
     *
     * @param {string} fileId - File identifier
     * @param {string} errorMessage - Error message
     */
    handleUploadError(fileId, errorMessage) {
        const previewItem = this.previewContainer.querySelector(`[data-file-id="${fileId}"]`);
        if (previewItem) {
            previewItem.classList.add('antek-upload-error');

            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'antek-upload-error-message';
            errorDiv.textContent = errorMessage;
            previewItem.appendChild(errorDiv);

            // Change cancel to retry
            const cancelBtn = previewItem.querySelector('.antek-file-cancel');
            if (cancelBtn) {
                cancelBtn.textContent = '×';
                cancelBtn.onclick = () => this.removePreview(fileId);
            }
        }

        // Call error callback
        this.onUploadError({
            fileId: fileId,
            error: errorMessage
        });
    }

    /**
     * Update upload progress
     *
     * @param {string} fileId - File identifier
     * @param {number} percent - Progress percentage
     */
    updateProgress(fileId, percent) {
        const previewItem = this.previewContainer.querySelector(`[data-file-id="${fileId}"]`);
        if (previewItem) {
            const progressBar = previewItem.querySelector('.antek-upload-progress-bar');
            if (progressBar) {
                progressBar.style.width = `${percent}%`;
            }
        }
    }

    /**
     * Cancel ongoing upload
     *
     * @param {string} fileId - File identifier
     */
    cancelUpload(fileId) {
        const xhr = this.currentUploads.get(fileId);
        if (xhr) {
            xhr.abort();
        }
        this.removePreview(fileId);
    }

    /**
     * Remove preview item
     *
     * @param {string} fileId - File identifier
     */
    removePreview(fileId) {
        const previewItem = this.previewContainer.querySelector(`[data-file-id="${fileId}"]`);
        if (previewItem) {
            previewItem.remove();
        }
    }

    /**
     * Show error message
     *
     * @param {string} message - Error message
     */
    showError(message) {
        // Create temporary error notification
        const errorDiv = document.createElement('div');
        errorDiv.className = 'antek-upload-error-toast';
        errorDiv.textContent = message;

        this.previewContainer.appendChild(errorDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }

    /**
     * Generate unique file ID
     *
     * @param {File} file - File object
     * @return {string} Unique identifier
     */
    generateFileId(file) {
        return `${file.name}-${file.size}-${Date.now()}`;
    }

    /**
     * Get file icon SVG
     *
     * @param {string} fileType - File type category
     * @return {string} SVG icon
     */
    getFileIcon(fileType) {
        const icons = {
            audio: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>',
            video: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>',
            document: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>',
            unknown: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 2c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6H6zm7 7V3.5L18.5 9H13z"/></svg>'
        };

        return icons[fileType] || icons.unknown;
    }

    /**
     * Format file size
     *
     * @param {number} bytes - File size in bytes
     * @return {string} Formatted size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Escape HTML
     *
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Clear all previews
     */
    clearPreviews() {
        this.previewContainer.innerHTML = '';
        this.currentUploads.clear();
    }

    /**
     * Get uploaded files
     *
     * @return {Array} Array of completed uploads
     */
    getUploadedFiles() {
        const completedUploads = [];
        const items = this.previewContainer.querySelectorAll('.antek-upload-complete');

        items.forEach(item => {
            const fileId = item.dataset.fileId;
            const fileName = item.querySelector('.antek-file-name').textContent;
            completedUploads.push({
                fileId: fileId,
                fileName: fileName
            });
        });

        return completedUploads;
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AntekFileUploader;
}
