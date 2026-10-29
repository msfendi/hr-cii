/**
 * Helper face-api.js untuk Chutex HRIS.
 *
 * Dipakai oleh:
 *  - halaman login (Face Recognition)
 *  - halaman login slip payroll (Employee Payroll)
 *  - menu Manage Face Recognition (register & test)
 *
 * Membutuhkan face-api.js (global `faceapi`) yang sudah dimuat lebih dulu.
 */
(function (global) {
    'use strict';

    var state = {
        modelsLoaded: false,
        loadingPromise: null,
        stream: null,
    };

    var DEFAULT_DETECTOR_OPTIONS = {
        inputSize: 416,
        scoreThreshold: 0.5,
    };

    function ensureFaceApi() {
        if (!global.faceapi) {
            throw new Error('face-api.js belum dimuat.');
        }

        return global.faceapi;
    }

    /**
     * Muat model face-api.js dari folder public/vendor-face-api/model.
     */
    function loadModels(modelUrl) {
        var faceapi = ensureFaceApi();

        if (state.modelsLoaded) {
            return Promise.resolve(true);
        }

        if (!state.loadingPromise) {
            state.loadingPromise = Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(modelUrl),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl),
            ]).then(function () {
                state.modelsLoaded = true;
                return true;
            }).catch(function (error) {
                state.loadingPromise = null;
                throw error;
            });
        }

        return state.loadingPromise;
    }

    /**
     * Nyalakan kamera dan pasang stream ke elemen <video>.
     */
    function startCamera(video, constraints) {
        if (!video) {
            return Promise.reject(new Error('Elemen video tidak ditemukan.'));
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.reject(new Error('Browser tidak mendukung akses kamera.'));
        }

        stopCamera(video);

        return navigator.mediaDevices.getUserMedia({
            video: constraints || {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 },
            },
            audio: false,
        }).then(function (stream) {
            state.stream = stream;
            video.srcObject = stream;
            video.setAttribute('playsinline', 'true');
            video.muted = true;

            return new Promise(function (resolve) {
                if (video.readyState >= 2) {
                    resolve();
                    return;
                }

                video.onloadedmetadata = function () {
                    resolve();
                };
            });
        }).then(function () {
            return video.play();
        }).then(function () {
            return state.stream;
        });
    }

    /**
     * Matikan kamera dan lepaskan stream dari elemen <video>.
     */
    function stopCamera(video) {
        if (state.stream) {
            state.stream.getTracks().forEach(function (track) {
                track.stop();
            });

            state.stream = null;
        }

        if (video) {
            try {
                video.pause();
            } catch (error) {
                // abaikan
            }

            video.srcObject = null;
        }
    }

    /**
     * Deteksi satu wajah dan hitung descriptor 128 dimensi.
     */
    function detect(video, options) {
        var faceapi = ensureFaceApi();

        var detectorOptions = new faceapi.TinyFaceDetectorOptions(
            Object.assign({}, DEFAULT_DETECTOR_OPTIONS, options || {})
        );

        return faceapi.detectSingleFace(video, detectorOptions)
            .withFaceLandmarks(true)
            .withFaceDescriptor()
            .then(function (result) {
                if (!result) {
                    return null;
                }

                return {
                    descriptor: Array.from(result.descriptor),
                    score: result.detection.score,
                    box: result.detection.box,
                };
            });
    }

    /**
     * Ambil frame video sebagai base64 JPEG.
     */
    function snapshot(video, maxWidth) {
        if (!video || !video.videoWidth) {
            return null;
        }

        var width  = Math.min(maxWidth || 480, video.videoWidth);
        var ratio  = width / video.videoWidth;
        var height = Math.round(video.videoHeight * ratio);
        var canvas = document.createElement('canvas');

        canvas.width  = width;
        canvas.height = height;

        var context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, width, height);

        return canvas.toDataURL('image/jpeg', 0.85);
    }

    /**
     * Helper fetch JSON.
     */
    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                    ? document.querySelector('meta[name="csrf-token"]').content
                    : '',
            },
            body: JSON.stringify(payload),
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    status: false,
                    message: 'Respon server tidak valid (HTTP ' + response.status + ').',
                };
            }).then(function (data) {
                if (!response.ok && data.message === undefined) {
                    data.message = 'Terjadi kesalahan (HTTP ' + response.status + ').';
                }

                return data;
            });
        });
    }

    global.FaceRecognition = {
        loadModels: loadModels,
        startCamera: startCamera,
        stopCamera: stopCamera,
        detect: detect,
        snapshot: snapshot,
        postJson: postJson,
    };
})(window);
