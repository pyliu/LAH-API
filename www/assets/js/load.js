/* synchronous loading js/css files */
/* store loaded code */
let loadedCodes = [];
window.Import = {
    /**
     * load a batch files
     * _files: array of file path which includes js, css or less file
     * succeed_callback: callback when succeed completion
     **/
    load: (_files, succeed_callback) => {
        /**
         * load a single file
         * url: file path
         * success_callback: loaded callback on success
         **/
        function appendTag(url, success_callback) {
            if (!isLoaded(loadedCodes, url)) {
                let this_type = getFileType(url);
                let fileObj = null;
                if (this_type == ".js") {
                    fileObj = document.createElement('script');
                    fileObj.src = url;
                } else if (this_type == ".css") {
                    fileObj = document.createElement('link');
                    fileObj.href = url;
                    fileObj.type = "text/css";
                    fileObj.rel = "stylesheet";
                } else if (this_type == ".less") {
                    fileObj = document.createElement('link');
                    fileObj.href = url;
                    fileObj.type = "text/css";
                    fileObj.rel = "stylesheet/less";
                }
                success_callback = success_callback || function() {};
                fileObj.onload = fileObj.onreadystatechange = () => {
                    if (!this.readyState || 'loaded' === this.readyState || 'complete' === this.readyState) {
                        success_callback();
                        loadedCodes.push(url)
                    }
                }
                document.getElementsByTagName('head')[0].appendChild(fileObj);
            } else {
                success_callback();
            }
        }
        /**
         * get file ext, lowercase
         **/
        function getFileType(url) {
            if (url != null && url.length > 0) {
                return url.substr(url.lastIndexOf(".")).toLowerCase();
            }
            return "";
        }
        /**
         * check if all files are loaded
         **/
        function isLoaded(FileArray, _url) {
            if (FileArray != null && FileArray.length > 0) {
                let len = FileArray.length;
                for (let i = 0; i < len; i++) {
                    if (FileArray[i] == _url) {
                        return true;
                    }
                }
            }
            return false;
        }

        let toload = [];
        if (typeof _files === "object") {
            toload = _files;
        } else {
            // string separated by comma
            if (typeof _files === "string") {
                toload = _files.split(",");
            }
        }
        if (toload != null && toload.length > 0) {
            let LoadedCount = 0;
            for (let i = 0; i < toload.length; i++) {
                appendTag(toload[i], () => {
                    LoadedCount++;
                    if (LoadedCount == toload.length) {
                        succeed_callback();
                    }
                })
			}
        }
    }
}
/**
 * // usage
 * let FilesArray = ["js/vue.js", "js/global.js", "js/jquery.js", "css/main.css"];
 * Import.load(FilesArray, function() {
 *   // after loaded code here
 * });
 */
