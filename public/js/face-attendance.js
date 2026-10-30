/* ============================================================
   Face Attendance GPS - Frontend Logic
   Kamera, Face-api.js, GPS, Liveness, Leaflet.draw, Turf.js
   Tidak menyentuh coding lama (self_register, face-recognition.js)
   ============================================================ */

(function () {
    'use strict';

    // ----------------------------------------------------------
    // Konfigurasi (di-set oleh Blade via window.FaceAttendanceConfig)
    // ----------------------------------------------------------
    const CFG = window.FaceAttendanceConfig || {};

    const CONFIG = {
        faceModelUrl: CFG.faceModelUrl || '',
        storeRoute: CFG.storeRoute || '',
        recapRoute: CFG.recapRoute || '',
        csrfToken: CFG.csrfToken || '',
        defaultClockIn: '08:00',
        defaultClockOut: '17:00',
        maxAccuracyMeters: 30,
        minConfidence: 0.6,
        livenessBlinkCount: 3,
    };

    // ----------------------------------------------------------
    // State
    // ----------------------------------------------------------
    const state = {
        stream: null,
        video: null,
        canvas: null,
        ctx: null,
        faceCanvas: null,
        faceCtx: null,
        isStreaming: false,
        isDetecting: false,
        isLivenessActive: false,
        blinkCount: 0,
        lastEyeStatus: null,
        descriptors: null,
        currentLocation: null,
        polygonGeoJSON: null,
        insidePolygon: false,
        mediaRecorder: null,
        recordedChunks: [],
    };

    // ----------------------------------------------------------
    // DOM Helpers
    // ----------------------------------------------------------
    const $ = (id) => document.getElementById(id);

    // ----------------------------------------------------------
    // Inisialisasi
    // ----------------------------------------------------------
    document.addEventListener('DOMContentLoaded', async () => {
        state.video = $('video');
        state.canvas = $('canvas');
        state.ctx = state.canvas ? state.canvas.getContext('2d') : null;
        state.faceCanvas = $('face-canvas');
        state.faceCtx = state.faceCanvas ? state.faceCanvas.getContext('2d') : null;

        // Load face-api models
        await loadFaceModels();

        // Setup GPS watch (high accuracy)
        setupGPS();

        // Load polygon dari server (jika ada di halaman index)
        if ($('polygon-data')) {
            state.polygonGeoJSON = JSON.parse($('polygon-data').textContent);
            state.insidePolygon = $('inside-polygon') && $('inside-polygon').textContent === '1';
            updateLocationStatus();
        }

        // Setup Leaflet draw (halaman map HRD)
        if ($('map')) {
            initLeafletMap();
        }
    });

    // ----------------------------------------------------------
    // Face-api.js model loading
    // ----------------------------------------------------------
    async function loadFaceModels() {
        const base = CONFIG.faceModelUrl;
        await faceapi.nets.tinyFaceDetector.loadFromUri(base);
        await faceapi.nets.faceLandmark68TinyNet.loadFromUri(base);
        await faceapi.nets.faceRecognitionNet.loadFromUri(base);
        console.log('Face-api models loaded');
    }

    // ----------------------------------------------------------
    // GPS - high accuracy, anti fake GPS
    // ----------------------------------------------------------
    function setupGPS() {
        if (!navigator.geolocation) {
            showAlert('Browser tidak mendukung Geolocation', 'error');
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
        };

        navigator.geolocation.watchPosition(
            (pos) => {
                state.currentLocation = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                };

                if (pos.coords.accuracy > CONFIG.maxAccuracyMeters) {
                    showWarning(`Akurasi GPS terlalu rendah: ${Math.round(pos.coords.accuracy)}m. Gerakkan ke area terbuka.`);
                } else {
                    hideWarning();
                }

                if (state.polygonGeoJSON) {
                    state.insidePolygon = pointInPolygon(
                        state.currentLocation.lat,
                        state.currentLocation.lng,
                        state.polygonGeoJSON.geometry.coordinates[0]
                    );
                    updateLocationStatus();
                }

                updateGPSCoordsDisplay();
            },
            (err) => {
                console.error('GPS error:', err);
                showAlert('Gagal mengambil lokasi GPS. Pastikan izin lokasi diberikan.', 'error');
            },
            options
        );
    }

    function updateGPSCoordsDisplay() {
        if (!state.currentLocation) return;
        const latEl = $('gps-lat');
        const lngEl = $('gps-lng');
        const accEl = $('gps-acc');
        if (latEl) latEl.textContent = state.currentLocation.lat.toFixed(6);
        if (lngEl) lngEl.textContent = state.currentLocation.lng.toFixed(6);
        if (accEl) accEl.textContent = Math.round(state.currentLocation.accuracy) + ' m';
    }

    function updateLocationStatus() {
        const badge = $('location-status');
        const insideBox = $('inside-polygon-box');
        if (!badge) return;

        if (!state.currentLocation) {
            badge.textContent = 'Mencari lokasi...';
            badge.className = 'overlay-badge warn';
            return;
        }

        if (state.insidePolygon) {
            badge.textContent = 'DI DALAM AREA';
            badge.className = 'overlay-badge ok';
            if (insideBox) insideBox.textContent = '✓ Di dalam batas lokasi kerja';
        } else {
            badge.textContent = 'DI LUAR AREA';
            badge.className = 'overlay-badge err';
            if (insideBox) insideBox.textContent = '✗ Di luar batas lokasi kerja';
        }
    }

    // ----------------------------------------------------------
    // Point in polygon (ray-casting)
    // ----------------------------------------------------------
    function pointInPolygon(lat, lng, ring) {
        let inside = false;
        const x = lng, y = lat;
        let j = ring.length - 1;
        for (let i = 0; i < ring.length; i++) {
            const xi = ring[i][0], yi = ring[i][1];
            const xj = ring[j][0], yj = ring[j][1];
            const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
            j = i;
        }
        return inside;
    }

    // ----------------------------------------------------------
    // Camera
    // ----------------------------------------------------------
    async function startCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 400, height: 400, facingMode: 'user' },
            });
            state.stream = stream;
            state.video.srcObject = stream;
            state.isStreaming = true;

            state.video.onloadedmetadata = () => {
                state.canvas.width = state.video.videoWidth;
                state.canvas.height = state.video.videoHeight;
                state.faceCanvas.width = state.video.videoWidth;
                state.faceCanvas.height = state.video.videoHeight;
            };

            $('btn-start-cam').disabled = true;
            $('btn-capture').disabled = false;
            showAlert('Kamera sudah aktif. Silakan posisikan wajah di tengah frame.', 'success');
        } catch (err) {
            console.error('Camera error:', err);
            showAlert('Gagal membuka kamera: ' + err.message, 'error');
        }
    }

    function stopCamera() {
        if (state.stream) {
            state.stream.getTracks().forEach((t) => t.stop());
            state.stream = null;
        }
        state.video.srcObject = null;
        state.isStreaming = false;
        $('btn-start-cam').disabled = false;
        $('btn-capture').disabled = true;
        if (state.ctx) state.ctx.clearRect(0, 0, state.canvas.width, state.canvas.height);
        if (state.faceCtx) state.faceCtx.clearRect(0, 0, state.faceCanvas.width, state.faceCanvas.height);
    }

    // ----------------------------------------------------------
    // Face detection & matching
    // ----------------------------------------------------------
    async function detectFace() {
        if (state.isDetecting) return;
        if (!state.isStreaming) {
            showAlert('Nyalakan kamera terlebih dahulu.', 'warning');
            return;
        }

        state.isDetecting = true;
        const btn = $('btn-detect');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="pulse-dot"></span> Memindai wajah...';
        }

        try {
            if (state.ctx) state.ctx.drawImage(state.video, 0, 0, state.canvas.width, state.canvas.height);

            const detections = await faceapi
                .detectAllFaces(state.canvas, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptors();

            if (detections.length === 0) {
                showAlert('Wajah tidak terdeteksi. Posisikan wajah ke tengah kamera.', 'warning');
                return;
            }

            if (detections.length > 1) {
                showAlert('Terlalu banyak wajah di frame. Pastikan hanya satu orang.', 'warning');
                return;
            }

            const detection = detections[0];
            drawFaceBox(detection);

            if (state.descriptors) {
                const distance = faceapi.euclideanDistance(detection.descriptor, state.descriptors);
                const match = distance < CONFIG.minConfidence;

                if (match) {
                    showAlert('Wajah cocok (' + distance.toFixed(3) + '). Silakan lakukan absensi.', 'success');
                    const ba = $('btn-absensi');
                    if (ba) ba.disabled = false;
                } else {
                    showAlert('Wajah tidak cocok (' + distance.toFixed(3) + '). Coba lagi.', 'error');
                    const ba = $('btn-absensi');
                    if (ba) ba.disabled = true;
                }
            } else {
                showAlert('Wajah terdeteksi. Silakan ambil foto untuk verifikasi.', 'info');
                const bc = $('btn-capture-photo');
                if (bc) bc.disabled = false;
            }
        } catch (err) {
            console.error('Detect error:', err);
            showAlert('Gagal mendeteksi wajah: ' + err.message, 'error');
        } finally {
            state.isDetecting = false;
            const btn = $('btn-detect');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Deteksi Wajah';
            }
        }
    }

    function drawFaceBox(detection) {
        if (!state.faceCtx) return;
        const box = detection.box;
        state.faceCtx.clearRect(0, 0, state.faceCanvas.width, state.faceCanvas.height);

        state.faceCtx.strokeStyle = '#10b981';
        state.faceCtx.lineWidth = 3;
        state.faceCtx.strokeRect(box.x, box.y, box.width, box.height);

        if (detection.landmarks) {
            state.faceCtx.fillStyle = '#10b981';
            detection.landmarks.getPositions().forEach((p) => {
                state.faceCtx.beginPath();
                state.faceCtx.arc(p.x, p.y, 2, 0, 2 * Math.PI);
                state.faceCtx.fill();
            });
        }
    }

    // ----------------------------------------------------------
    // Liveness (kedip mata)
    // ----------------------------------------------------------
    async function startLiveness() {
        if (!state.isStreaming) {
            showAlert('Nyalakan kamera terlebih dahulu.', 'warning');
            return;
        }

        state.isLivenessActive = true;
        state.blinkCount = 0;
        state.lastEyeStatus = null;
        const box = $('liveness-box');
        if (box) box.style.display = 'block';
        const count = $('liveness-count');
        if (count) count.textContent = '0 / ' + CONFIG.livenessBlinkCount;
        const btn = $('btn-liveness');
        if (btn) btn.disabled = true;

        detectLiveness();
    }

    async function detectLiveness() {
        if (!state.isLivenessActive) return;

        if (state.ctx) state.ctx.drawImage(state.video, 0, 0, state.canvas.width, state.canvas.height);

        const detections = await faceapi
            .detectAllFaces(state.canvas, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks();

        if (detections.length === 1) {
            const landmarks = detections[0].landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();

            const leftEAR = calculateEAR(leftEye);
            const rightEAR = calculateEAR(rightEye);
            const ear = (leftEAR + rightEAR) / 2;

            const eyeClosed = ear < 0.2;

            if (eyeClosed && state.lastEyeStatus === false) {
                state.blinkCount++;
                const count = $('liveness-count');
                if (count) count.textContent = state.blinkCount + ' / ' + CONFIG.livenessBlinkCount;
                showAlert('Kedip ke-' + state.blinkCount, 'info');
            }

            state.lastEyeStatus = !eyeClosed;
        }

        if (state.blinkCount >= CONFIG.livenessBlinkCount) {
            state.isLivenessActive = false;
            const status = $('liveness-status');
            if (status) {
                status.textContent = 'Liveness lulus';
                status.className = 'overlay-badge ok';
            }
            showAlert('Verifikasi liveness berhasil.', 'success');
            const ba = $('btn-absensi');
            if (ba) ba.disabled = false;
            return;
        }

        setTimeout(detectLiveness, 100);
    }

    function calculateEAR(eye) {
        const A = dist(eye[1], eye[5]);
        const B = dist(eye[2], eye[4]);
        const C = dist(eye[0], eye[3]);
        return (A + B) / (2.0 * C);
    }

    function dist(p1, p2) {
        return Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
    }

    function stopLiveness() {
        state.isLivenessActive = false;
        const box = $('liveness-box');
        if (box) box.style.display = 'none';
        const btn = $('btn-liveness');
        if (btn) btn.disabled = false;
    }

    // ----------------------------------------------------------
    // Capture photo (verifikasi wajah)
    // ----------------------------------------------------------
    async function capturePhoto() {
        if (!state.isStreaming) return;

        if (state.ctx) state.ctx.drawImage(state.video, 0, 0, state.canvas.width, state.canvas.height);
        const dataUrl = state.canvas.toDataURL('image/jpeg', 0.8);

        const formData = new FormData();
        formData.append('image', dataUrl);
        formData.append('_token', CONFIG.csrfToken);
        formData.append('face_verified', '1');

        try {
            const res = await fetch(CONFIG.storeRoute, {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                showAlert('Absensi berhasil disimpan.', 'success');
                setTimeout(() => {
                    window.location.href = CONFIG.recapRoute;
                }, 1500);
            } else {
                showAlert(data.message || 'Absensi gagal.', 'error');
            }
        } catch (err) {
            showAlert('Gagal mengirim absensi: ' + err.message, 'error');
        }
    }

    // ----------------------------------------------------------
    // Submit absensi (clock in/out)
    // ----------------------------------------------------------
    async function submitAbsensi() {
        if (!state.currentLocation) {
            showAlert('Lokasi GPS belum tersedia.', 'error');
            return;
        }

        if (!state.insidePolygon) {
            showAlert('Anda di luar area lokasi kerja. Tidak bisa absensi.', 'error');
            return;
        }

        if (state.currentLocation.accuracy > CONFIG.maxAccuracyMeters) {
            showAlert('Akurasi GPS terlalu rendah. Gerakkan ke area terbuka.', 'error');
            return;
        }

        const btn = $('btn-absensi');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="pulse-dot"></span> Mengirim...';
        }

        const formData = new FormData();
        formData.append('_token', CONFIG.csrfToken);
        formData.append('latitude', state.currentLocation.lat);
        formData.append('longitude', state.currentLocation.lng);
        formData.append('accuracy', state.currentLocation.accuracy);
        formData.append('face_verified', '1');
        formData.append('liveness_passed', state.isLivenessActive ? 1 : 0);

        try {
            const res = await fetch(CONFIG.storeRoute, {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                showAlert('Absensi berhasil: ' + data.type, 'success');
                if (btn) btn.disabled = true;
                setTimeout(() => {
                    window.location.href = CONFIG.recapRoute;
                }, 1500);
            } else {
                showAlert(data.message || 'Absensi gagal.', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Absensi';
                }
            }
        } catch (err) {
            showAlert('Gagal mengirim absensi: ' + err.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Absensi';
            }
        }
    }

    // ----------------------------------------------------------
    // Alerts
    // ----------------------------------------------------------
    function showAlert(msg, type = 'info') {
        const box = $('alert-box');
        if (!box) return;
        box.textContent = msg;
        box.className = 'alert alert-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info');
        box.style.display = 'block';

        if (type !== 'info') {
            setTimeout(() => { box.style.display = 'none'; }, 4000);
        }
    }

    function showWarning(msg) {
        const box = $('gps-warning');
        if (!box) return;
        const msgEl = box.querySelector('.warning-msg');
        if (msgEl) msgEl.textContent = msg;
        box.style.display = 'flex';
    }

    function hideWarning() {
        const box = $('gps-warning');
        if (box) box.style.display = 'none';
    }

    // ----------------------------------------------------------
    // Leaflet Map + Draw (HRD)
    // ----------------------------------------------------------
    function initLeafletMap() {
        const map = L.map('map').setView([-6.2088, 106.8456], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
        }).addTo(map);

        let drawnLayer = null;
        const polygonData = $('polygon-data');
        if (polygonData) {
            const geo = JSON.parse(polygonData.textContent);
            const coords = geo.geometry.coordinates[0].map((c) => [c[1], c[0]]);
            drawnLayer = L.polygon(coords, { color: '#10b981', fillOpacity: 0.2 }).addTo(map);
            map.fitBounds(drawnLayer.getBounds());
        }

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            edit: { featureGroup: drawnItems },
            draw: {
                polygon: { allowIntersection: false, showArea: true },
                polyline: false,
                circle: false,
                circlemarker: false,
                marker: false,
                rectangle: false,
            },
        });
        map.addControl(drawControl);

        map.on(L.Draw.Event.CREATED, (e) => {
            drawnItems.clearLayers();
            drawnLayer = e.layer;
            drawnItems.addLayer(drawnLayer);

            const latlngs = drawnLayer.getLatLngs()[0];
            const geoCoords = latlngs.map((l) => [l.lng, l.lat]);
            const polyInput = $('polygon-geojson');
            if (polyInput) {
                polyInput.value = JSON.stringify({
                    type: 'Feature',
                    geometry: { type: 'Polygon', coordinates: [geoCoords] },
                });
            }
            showAlert('Polygon berhasil digambar. Klik Simpan Lokasi.', 'success');
        });

        map.on(L.Draw.Event.EDITED, (e) => {
            const latlngs = drawnLayer.getLatLngs()[0];
            const geoCoords = latlngs.map((l) => [l.lng, l.lat]);
            const polyInput = $('polygon-geojson');
            if (polyInput) {
                polyInput.value = JSON.stringify({
                    type: 'Feature',
                    geometry: { type: 'Polygon', coordinates: [geoCoords] },
                });
            }
        });

        window.leafletMap = map;
        window.leafletDrawnItems = drawnItems;
    }

    // ----------------------------------------------------------
    // Event listeners
    // ----------------------------------------------------------
    const btnStartCam = $('btn-start-cam');
    const btnStopCam = $('btn-stop-cam');
    const btnDetect = $('btn-detect');
    const btnCapture = $('btn-capture-photo');
    const btnAbsensi = $('btn-absensi');
    const btnLiveness = $('btn-liveness');
    const btnStopLiveness = $('btn-stop-liveness');

    if (btnStartCam) btnStartCam.addEventListener('click', startCamera);
    if (btnStopCam) btnStopCam.addEventListener('click', stopCamera);
    if (btnDetect) btnDetect.addEventListener('click', detectFace);
    if (btnCapture) btnCapture.addEventListener('click', capturePhoto);
    if (btnAbsensi) btnAbsensi.addEventListener('click', submitAbsensi);
    if (btnLiveness) btnLiveness.addEventListener('click', startLiveness);
    if (btnStopLiveness) btnStopLiveness.addEventListener('click', stopLiveness);

    // Expose globally for inline HTML handlers
    window.FaceAttendance = {
        startCamera,
        stopCamera,
        detectFace,
        capturePhoto,
        submitAbsensi,
        startLiveness,
        stopLiveness,
    };
})();
