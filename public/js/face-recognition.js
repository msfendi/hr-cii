/**
 * Helper face-api.js untuk Chutex HRIS.
 *
 * Dipakai oleh:
 *  - halaman login (Face Recognition)
 *  - halaman login slip payroll (Employee Payroll)
 *  - menu Manage Face Recognition (register & test)
 *  - menu Pengajuan Cuti (Face Recognition Login)
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
            throw new Error('face-api.js belum dimuat. Pastikan vendor-face-api/face-api.js sudah disertakan.');
        }

        return global.faceapi;
    }

    /**
     * Muat model face-api.js dari folder public/vendor-face-api/model dengan fallback multi-URL.
     */
    function loadModels(modelUrl) {
        var faceapi = ensureFaceApi();

        if (state.modelsLoaded) {
            return Promise.resolve(true);
        }

        if (state.loadingPromise) {
            return state.loadingPromise;
        }

        // Tentukan daftar kandidat URL folder model
        var candidateUrls = [];
        if (modelUrl) {
            candidateUrls.push(modelUrl);
        }

        var origin = window.location.origin;
        var pathname = window.location.pathname;
        var basePath = origin + pathname.substring(0, pathname.lastIndexOf('/'));

        candidateUrls.push('/vendor-face-api/model');
        candidateUrls.push('/models');
        candidateUrls.push('vendor-face-api/model');
        candidateUrls.push('models');
        candidateUrls.push(basePath + '/vendor-face-api/model');
        candidateUrls.push(basePath + '/models');

        // Filter duplikat & nilai kosong
        candidateUrls = candidateUrls.filter(function (item, pos) {
            return item && candidateUrls.indexOf(item) === pos;
        });

        function isAnyModelLoaded() {
            var detectorOk = (faceapi.nets.tinyFaceDetector && (faceapi.nets.tinyFaceDetector.isLoaded || faceapi.nets.tinyFaceDetector.params));
            var landmarkOk = (faceapi.nets.faceLandmark68Net && (faceapi.nets.faceLandmark68Net.isLoaded || faceapi.nets.faceLandmark68Net.params)) ||
                             (faceapi.nets.faceLandmark68TinyNet && (faceapi.nets.faceLandmark68TinyNet.isLoaded || faceapi.nets.faceLandmark68TinyNet.params));
            var recognizerOk = (faceapi.nets.faceRecognitionNet && (faceapi.nets.faceRecognitionNet.isLoaded || faceapi.nets.faceRecognitionNet.params));

            return !!(detectorOk && landmarkOk && recognizerOk);
        }

        function tryLoadFromUrlIndex(index) {
            if (index >= candidateUrls.length) {
                if (isAnyModelLoaded()) {
                    state.modelsLoaded = true;
                    return Promise.resolve(true);
                }
                state.loadingPromise = null;
                return Promise.reject(
                    new Error(
                        'Gagal memuat model face recognition dari seluruh lokasi URL kandidat. Periksa direktori public/vendor-face-api/model atau public/models.'
                    )
                );
            }

            var currentUrl = candidateUrls[index];

            var safeLoad = function (net) {
                if (net && typeof net.loadFromUri === 'function') {
                    return net.loadFromUri(currentUrl).catch(function () {
                        return null;
                    });
                }
                return Promise.resolve(null);
            };

            return Promise.all([
                safeLoad(faceapi.nets.tinyFaceDetector),
                safeLoad(faceapi.nets.faceLandmark68TinyNet),
                safeLoad(faceapi.nets.faceRecognitionNet),
            ])
                .then(function () {
                    if (isAnyModelLoaded()) {
                        state.modelsLoaded = true;
                        console.log('FaceRecognition models loaded successfully from:', currentUrl);
                        return true;
                    } else {
                        return tryLoadFromUrlIndex(index + 1);
                    }
                })
                .catch(function (err) {
                    console.warn('FaceRecognition loadModels try failed for URL:', currentUrl, err);
                    return tryLoadFromUrlIndex(index + 1);
                });
        }

        state.loadingPromise = tryLoadFromUrlIndex(0);
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

        return navigator.mediaDevices
            .getUserMedia({
                video: constraints || {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                },
                audio: false,
            })
            .then(function (stream) {
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
            })
            .then(function () {
                return video.play();
            })
            .then(function () {
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

        return faceapi
            .detectSingleFace(video, detectorOptions)
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

        var width = Math.min(maxWidth || 480, video.videoWidth);
        var ratio = width / video.videoWidth;
        var height = Math.round(video.videoHeight * ratio);
        var canvas = document.createElement('canvas');

        canvas.width = width;
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
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                    ? document.querySelector('meta[name="csrf-token"]').content
                    : '',
            },
            body: JSON.stringify(payload),
        }).then(function (response) {
            return response
                .json()
                .catch(function () {
                    return {
                        success: false,
                        status: false,
                        message: 'Respon server tidak valid (HTTP ' + response.status + ').',
                    };
                })
                .then(function (data) {
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
