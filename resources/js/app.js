import { extractBackContacts } from './scanner-contact-parser.js';

const viewer = document.querySelector('#floor-plan');
const mapStage = document.querySelector('#map-stage');
const cveLoader = document.querySelector('[data-cve-loader]');
const cveLoaderMessage = cveLoader?.querySelector('[data-cve-loader-message]');
const landingOrbit = document.querySelector('.landing-orbit');

if (landingOrbit) {
    [
        ['.orbit-track-people', 20],
        ['.orbit-track-rooms', 36],
        ['.orbit-track-time', 60],
    ].forEach(([selector, seconds]) => {
        const track = landingOrbit.querySelector(selector);
        const node = track?.querySelector('.orbit-node');
        const delay = `${-(Math.random() * seconds)}s`;
        track?.style.setProperty('animation-delay', delay);
        node?.style.setProperty('animation-delay', delay);
    });
}

const chatbotForm = document.querySelector('[data-chatbot-form]');
const chatbotMessages = document.querySelector('.chatbot-messages');
chatbotForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = chatbotForm.elements.question;
    const question = input.value.trim();
    if (!question || !chatbotMessages) return;
    const prompt = document.createElement('p');
    prompt.className = 'chatbot-message is-user';
    prompt.textContent = question;
    const answer = document.createElement('p');
    answer.className = 'chatbot-message';
    answer.textContent = 'Checking live campus records…';
    chatbotMessages.append(prompt, answer);
    input.value = '';
    const submitButton = chatbotForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;

    try {
        const response = await fetch('/api/help/ask', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ question }),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'The help service is unavailable. Try again.');

        answer.textContent = result.answer;
        if (Array.isArray(result.links) && result.links.length) {
            const links = document.createElement('p');
            links.className = 'chatbot-links';
            result.links.forEach(({ label, url }) => {
                const link = document.createElement('a');
                link.href = url;
                link.textContent = label;
                links.append(link);
            });
            chatbotMessages.append(links);
        }
    } catch (error) {
        answer.textContent = error instanceof Error ? error.message : 'The help service is unavailable. Try again.';
        answer.classList.add('is-error');
    } finally {
        submitButton.disabled = false;
        input.focus();
    }
});

if (viewer && mapStage && ['admin', 'creator'].includes(document.body.dataset.userRole)) {
    const fastNotice = document.createElement('p');
    fastNotice.className = 'map-data-note map-fast-mode';
    fastNotice.textContent = 'Floor plan preview is available to students. Use room data and reservations below.';
    viewer.replaceChildren(fastNotice);
}

function startStudentFloorPlan() {
    if (!viewer || !mapStage) return;

    showCveLoader('Preparing the interactive floor plan…');
    Promise.all([
        import('three'),
        import('three/addons/loaders/GLTFLoader.js'),
        import('three/addons/loaders/DRACOLoader.js'),
    ]).then(([THREE, { GLTFLoader }, { DRACOLoader }]) => {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color('#f0f8ff');
    const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 1000);
    camera.position.set(8, 7, 10);
    const cameraTarget = new THREE.Vector3(0, 0, 0);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x000000, 0);
    viewer.replaceChildren(renderer.domElement);
    renderer.domElement.setAttribute('aria-hidden', 'true');
    scene.add(new THREE.HemisphereLight(0xffffff, 0x4b687b, 2.2));
    const keyLight = new THREE.DirectionalLight(0xffffff, 2.6);
    keyLight.position.set(8, 12, 10);
    scene.add(keyLight);
    const modelRoot = new THREE.Group();
    scene.add(modelRoot);
    const dracoLoader = new DRACOLoader();
    dracoLoader.setDecoderPath(`${viewer.dataset.dracoUrl}/`);
    const loader = new GLTFLoader();
    loader.setDRACOLoader(dracoLoader);
    const floorBack = document.querySelector('#floor-back');
    const floorSelect = document.querySelector('#floor-select');
    const floorLabel = document.querySelector('#map-floor-label');
    const roomList = document.querySelector('#room-status-list');
    const endpoint = roomList?.dataset.endpoint;
    const upcomingList = document.querySelector('#upcoming-list');
    const roomCount = document.querySelector('#room-count');
    const roomLoadMessage = document.querySelector('#room-load-message');
    const floorDataEndpoint = viewer.dataset.floorDataEndpoint || endpoint;
    const roomDetail = document.querySelector('#room-detail');
    const statusLabels = { available: 'Available', occupied: 'Occupied', reserved: 'Reserved', maintenance: 'Maintenance', unavailable: 'Unavailable' };
    let floorGroups = [];
    let hoveredFloor = null;
    let selectedFloor = null;
    let dragging = false;
    let lastPointer = null;
    let zoom = 1;
    let viewpointMode = '3d';
    const floorModels = JSON.parse(viewer.dataset.floorModels || '[]');
    const mapStageActions = document.querySelectorAll('[data-map-action]');
    const viewpointButton = document.querySelector('[data-map-action="viewpoint"]');

    function renderRoomRows(rooms, floor) {
        if (!roomList) return;
        roomList.replaceChildren();
        rooms.forEach((room) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'room-status-row';
            row.dataset.roomId = room.id;

            const number = document.createElement('span');
            number.className = 'room-number';
            number.textContent = room.id;
            const group = document.createElement('span');
            group.className = 'room-name-group';
            const name = document.createElement('strong');
            name.textContent = room.name;
            const facts = document.createElement('small');
            const floorNumber = floor === null ? room.floor + 1 : floor + 1;
            const suffix = floorNumber === 2 ? 'nd' : (floorNumber === 3 ? 'rd' : 'th');
            facts.textContent = `${floorNumber}${suffix} floor · ${room.capacity} seats`;
            group.append(name, facts);
            const badge = document.createElement('span');
            badge.className = `status-badge ${room.status}`;
            badge.textContent = statusLabels[room.status] || 'Unknown status';
            row.append(number, group, badge);
            roomList.append(row);
        });
        if (!rooms.length) {
            const empty = document.createElement('p');
            empty.className = 'room-empty-state';
            empty.textContent = 'No rooms are available right now.';
            roomList.append(empty);
        }
        if (roomCount) roomCount.innerHTML = `${rooms.length} <small>rooms</small>`;
    }

    function renderUpcoming(items) {
        if (!upcomingList) return;
        upcomingList.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('li');
            empty.className = 'upcoming-empty';
            empty.textContent = 'No reservations scheduled for this floor today.';
            upcomingList.append(empty);
            return;
        }
        items.forEach((item, index) => {
            const starts = new Date(item.starts_at);
            const ends = new Date(item.ends_at);
            const row = document.createElement('li');
            const time = document.createElement('span');
            time.className = 'schedule-time';
            time.textContent = starts.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const track = document.createElement('span');
            track.className = 'schedule-track';
            const dot = document.createElement('i');
            dot.className = `track-dot ${index % 2 ? 'track-amber' : 'track-blue'}`;
            track.append(dot);
            const details = document.createElement('span');
            details.className = 'schedule-details';
            const title = document.createElement('strong');
            title.textContent = item.title;
            const meta = document.createElement('small');
            meta.textContent = `${item.room} · ${starts.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}–${ends.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
            details.append(title, meta);
            const state = document.createElement('span');
            state.className = `reservation-state ${item.status === 'pending' ? 'is-pending' : ''}`;
            state.textContent = item.status === 'pending' ? 'Pending' : 'Approved';
            row.append(time, track, details, state);
            upcomingList.append(row);
        });
    }

    function showRoomDetail(room) {
        if (!roomDetail) return;
        document.querySelector('#detail-room-id').textContent = room.id;
        document.querySelector('#detail-room-name').textContent = room.name;
        document.querySelector('#detail-capacity').textContent = `${room.capacity} seats`;
        document.querySelector('#detail-equipment').textContent = room.equipment;
        document.querySelector('#detail-next').textContent = room.next;
        const status = document.querySelector('#detail-status');
        status.className = `detail-status ${room.status}`;
        status.textContent = statusLabels[room.status] || 'Unknown status';
        roomDetail.hidden = false;
    }

    async function loadFloorData(floor) {
        if (!floorDataEndpoint) return;
        const url = new URL(floorDataEndpoint, window.location.origin);
        if (floor && floor !== 'all') url.searchParams.set('floor', floor);
        roomLoadMessage?.removeAttribute('hidden');
        if (roomLoadMessage) roomLoadMessage.textContent = 'Refreshing room availability…';
        showCveLoader('Refreshing room availability…');
        document.querySelector('.availability-panel')?.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Floor data request failed with status ${response.status}.`);
            const payload = await response.json();
            renderRoomRows(payload.data || [], floor === 'all' ? null : Number(floor));
            renderUpcoming(payload.upcoming || []);
            if (roomLoadMessage) roomLoadMessage.textContent = `${(payload.data || []).length} rooms synchronized from the database.`;
        } catch (error) {
            if (roomLoadMessage) roomLoadMessage.textContent = 'Room data could not be refreshed. Showing the last loaded state.';
        } finally {
            document.querySelector('.availability-panel')?.setAttribute('aria-busy', 'false');
            window.setTimeout(() => roomLoadMessage?.setAttribute('hidden', ''), 2400);
            hideCveLoader();
        }
    }

    function resize() {
        const bounds = viewer.getBoundingClientRect();
        camera.aspect = Math.max(bounds.width, 1) / Math.max(bounds.height, 1);
        camera.updateProjectionMatrix();
        renderer.setSize(Math.max(bounds.width, 1), Math.max(bounds.height, 1), false);
    }

    function setMaterialHighlight(root, active) {
        root.traverse((node) => {
            if (!node.isMesh || !node.material) return;
            const materials = Array.isArray(node.material) ? node.material : [node.material];
            materials.forEach((material) => {
                if (!material.emissive) return;
                if (!material.userData.cveBaseEmissive) material.userData.cveBaseEmissive = material.emissive.clone();
                material.emissive.copy(active ? new THREE.Color('#a43d43') : material.userData.cveBaseEmissive);
                material.emissiveIntensity = active ? 0.45 : 0.08;
            });
        });
    }

    function showAllFloors() {
        selectedFloor = null;
        modelRoot.children.forEach((group) => { group.visible = true; setMaterialHighlight(group, group === hoveredFloor); });
        if (floorLabel) floorLabel.textContent = 'ALL FLOORS';
        floorBack?.setAttribute('hidden', '');
    }

    function focusFloor(group, index = floorGroups.indexOf(group)) {
        selectedFloor = group;
        if (hoveredFloor) {
            setMaterialHighlight(hoveredFloor, false);
            hoveredFloor = null;
        }
        // Always apply visibility to the model root's direct children. This prevents a
        // previously selected floor from remaining visible when the dropdown changes.
        modelRoot.children.forEach((candidate) => {
            const isSelected = candidate === group;
            candidate.visible = isSelected;
            setMaterialHighlight(candidate, false);
        });
        if (floorLabel) floorLabel.textContent = index === 3 ? 'ROOF' : `FLOOR ${index + 2}`;
        floorBack?.removeAttribute('hidden');
    }

    function getFloorGroup(object) {
        let current = object;
        while (current && current.parent && current.parent !== modelRoot) current = current.parent;
        return current?.parent === modelRoot ? current : null;
    }

    function render() {
        requestAnimationFrame(render);
        if (viewpointMode === '2d') {
            camera.position.set(0, 16 / zoom, 0.01).add(cameraTarget);
        } else {
            camera.position.set(8 / zoom, 7 / zoom, 10 / zoom).add(cameraTarget);
        }
        camera.lookAt(cameraTarget);
        renderer.render(scene, camera);
    }

    function setZoom(nextZoom) {
        zoom = Math.max(0.5, Math.min(5, nextZoom));
    }

    function recenter() {
        modelRoot.rotation.set(0, 0, 0);
        setZoom(1);
        cameraTarget.set(0, 0, 0);
    }

    function updateViewpointLabel() {
        if (!viewpointButton) return;
        const label = viewpointMode === '2d' ? 'Viewpoint: 2D plan (locked)' : 'Lock 2D plan view';
        viewpointButton.setAttribute('aria-label', label);
        viewpointButton.setAttribute('title', label);
        viewpointButton.dataset.tooltip = label;
    }

    async function toggleFullscreen() {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
            return;
        }

        await mapStage.requestFullscreen?.();
    }

    mapStageActions.forEach((button) => {
        button.addEventListener('click', async () => {
            switch (button.dataset.mapAction) {
                case 'zoom-in':
                    setZoom(zoom * 1.35);
                    break;
                case 'zoom-out':
                    setZoom(zoom / 1.35);
                    break;
                case 'center':
                    recenter();
                    break;
                case 'rotate':
                    modelRoot.rotation.y += Math.PI / 4;
                    break;
                case 'viewpoint':
                    viewpointMode = viewpointMode === '2d' ? '3d' : '2d';
                    if (viewpointMode === '2d') {
                        modelRoot.rotation.set(0, 0, 0);
                        setZoom(1);
                    }
                    updateViewpointLabel();
                    break;
                case 'fullscreen':
                    await toggleFullscreen();
                    resize();
                    break;
            }
        });
    });
    updateViewpointLabel();
    Promise.all(floorModels.map((url) => loader.loadAsync(url))).then((assets) => {
        modelRoot.clear();
        floorGroups = assets.map((asset) => asset.scene);
        floorGroups.forEach((group) => modelRoot.add(group));
        const box = new THREE.Box3().setFromObject(modelRoot);
        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        const modelScale = 6 / Math.max(size.x, size.y, size.z, 1);
        floorGroups.forEach((group) => group.position.sub(center));
        modelRoot.scale.setScalar(modelScale);
        modelRoot.position.set(0, 0, 0);
        const framedBox = new THREE.Box3().setFromObject(modelRoot);
        const framedSphere = framedBox.getBoundingSphere(new THREE.Sphere());
        cameraTarget.set(0, 0, 0);
        const distance = Math.max(framedSphere.radius * 2.4, 8);
        camera.position.set(distance * 0.82, distance * 0.64, distance).add(cameraTarget);
        camera.near = Math.max(0.01, distance / 100);
        camera.far = distance * 100;
        camera.updateProjectionMatrix();
        const selectedValue = floorSelect?.value || 'all';
        const selectedIndex = Math.max(0, Number(selectedValue) - 1);
        if (selectedValue !== 'all' && floorGroups[selectedIndex]) {
            focusFloor(floorGroups[selectedIndex], selectedIndex);
        } else {
            showAllFloors();
        }
    }).catch(() => {
        const message = document.createElement('p');
        message.className = 'map-data-note';
        message.textContent = 'The NPC 3D model could not be loaded.';
        mapStage.append(message);
    }).finally(() => {
        hideCveLoader();
        loadFloorData(floorSelect?.value || 'all');
    });

    viewer.addEventListener('pointerdown', (event) => { dragging = true; lastPointer = { x: event.clientX, y: event.clientY }; viewer.setPointerCapture(event.pointerId); });
    viewer.addEventListener('pointermove', (event) => {
        if (!dragging || !lastPointer) return;
        const panGesture = viewpointMode === '2d' || event.shiftKey || event.ctrlKey;
        if (panGesture) {
            const panScale = 0.012 / zoom;
            cameraTarget.x -= (event.clientX - lastPointer.x) * panScale;
            cameraTarget.z += (event.clientY - lastPointer.y) * panScale;
            lastPointer = { x: event.clientX, y: event.clientY };
            return;
        }
        modelRoot.rotation.y += (event.clientX - lastPointer.x) * 0.008;
        modelRoot.rotation.x = Math.max(-0.65, Math.min(0.65, modelRoot.rotation.x + (event.clientY - lastPointer.y) * 0.006));
        lastPointer = { x: event.clientX, y: event.clientY };
    });
    viewer.addEventListener('pointerup', (event) => { dragging = false; lastPointer = null; viewer.releasePointerCapture(event.pointerId); });
    viewer.addEventListener('wheel', (event) => { event.preventDefault(); setZoom(zoom * (event.deltaY < 0 ? 1.12 : 0.89)); }, { passive: false });
    viewer.addEventListener('click', (event) => {
        const bounds = viewer.getBoundingClientRect();
        const pointer = new THREE.Vector2(((event.clientX - bounds.left) / bounds.width) * 2 - 1, -((event.clientY - bounds.top) / bounds.height) * 2 + 1);
        const raycaster = new THREE.Raycaster();
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(floorGroups.filter((group) => group.visible), true)[0];
        const group = hit ? getFloorGroup(hit.object) : null;
        if (group) focusFloor(group);
    });
    viewer.addEventListener('mousemove', (event) => {
        if (selectedFloor) return;
        const bounds = viewer.getBoundingClientRect();
        const pointer = new THREE.Vector2(((event.clientX - bounds.left) / bounds.width) * 2 - 1, -((event.clientY - bounds.top) / bounds.height) * 2 + 1);
        const raycaster = new THREE.Raycaster();
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(floorGroups.filter((group) => group.visible), true)[0];
        const group = hit ? getFloorGroup(hit.object) : null;
        if (group !== hoveredFloor) { if (hoveredFloor) setMaterialHighlight(hoveredFloor, false); hoveredFloor = group; if (hoveredFloor) setMaterialHighlight(hoveredFloor, true); }
    });
    floorBack?.addEventListener('click', showAllFloors);
    roomList?.addEventListener('click', (event) => {
        const row = event.target.closest('[data-room-id]');
        if (!row) return;
        const badge = row.querySelector('.status-badge');
        showRoomDetail({
            id: row.dataset.roomId,
            name: row.querySelector('strong')?.textContent || row.dataset.roomId,
            capacity: row.querySelector('small')?.textContent.match(/(\d+) seats/)?.[1] || '—',
            equipment: 'Campus equipment inventory',
            next: 'See room availability',
            status: [...(badge?.classList || [])].find((name) => Object.hasOwn(statusLabels, name)) || 'available',
        });
    });
    document.querySelector('#close-room-detail')?.addEventListener('click', () => { if (roomDetail) roomDetail.hidden = true; });
    floorSelect?.addEventListener('change', () => {
        const selected = Math.max(0, Number(floorSelect.value) - 1);
        if (floorSelect.value === 'all') showAllFloors();
        else if (floorGroups[selected]) focusFloor(floorGroups[selected], selected);
        loadFloorData(floorSelect.value);
    });
    new ResizeObserver(resize).observe(viewer);
    resize();
    render();
}).catch(() => {
        const message = document.createElement('p');
        message.className = 'map-data-note';
        message.textContent = 'The interactive floor plan could not be started.';
        mapStage.append(message);
        hideCveLoader();
    });
}

if (viewer && mapStage && !['admin', 'creator'].includes(document.body.dataset.userRole)) {
    if (typeof IntersectionObserver === 'undefined') {
        startStudentFloorPlan();
    } else {
        const floorPlanObserver = new IntersectionObserver((entries, observer) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;

            observer.disconnect();
            startStudentFloorPlan();
        }, { rootMargin: '80px 0px' });

        floorPlanObserver.observe(viewer);
    }
}

const yieldToBrowser = () => new Promise((resolve) => window.requestAnimationFrame(() => resolve()));

function withTimeout(promise, timeoutMs, message) {
    let timer;
    const timeout = new Promise((_, reject) => {
        timer = window.setTimeout(() => reject(new Error(message)), timeoutMs);
    });

    return Promise.race([promise, timeout]).finally(() => window.clearTimeout(timer));
}

function showCveLoader(message = 'Working on it…') {
    if (!cveLoader) return;
    if (cveLoaderMessage) cveLoaderMessage.textContent = message;
    cveLoader.hidden = false;
}

function hideCveLoader() {
    if (cveLoader) cveLoader.hidden = true;
}

document.querySelectorAll('[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        showCveLoader(form.dataset.loadingMessage || 'Validating your request…');
        form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
    });
});

document.addEventListener('click', (event) => {
    const action = event.target.closest('a, button, summary');
    if (!action || action.disabled || action.getAttribute('aria-disabled') === 'true') return;

    action.classList.add('cve-action-pulse');
    window.setTimeout(() => action.classList.remove('cve-action-pulse'), 180);

    const link = action.closest('a');
    const isNavigation = link
        && link.href
        && link.origin === window.location.origin
        && !link.hasAttribute('download')
        && link.target !== '_blank'
        && !link.href.includes('#')
        && !action.matches('[data-no-loader]');
    const isExplicitAction = action.matches('[data-loading-action]');
    if (isNavigation || isExplicitAction) {
        showCveLoader(action.dataset.loadingMessage || (isNavigation ? 'Opening the next page…' : 'Working on it…'));
    }
});

const reservationForm = document.querySelector('[data-reservation-form]');

reservationForm?.elements.starts_at?.addEventListener('input', (event) => {
    const endsAt = reservationForm.elements.ends_at;
    if (endsAt.dataset.userEdited === 'true' || !event.currentTarget.value) return;

    const durationMinutes = Number.parseInt(reservationForm.dataset.defaultDuration || '120', 10);
    const startsAt = new Date(event.currentTarget.value);
    if (Number.isNaN(startsAt.getTime()) || !Number.isFinite(durationMinutes)) return;

    const proposedEnd = new Date(startsAt.getTime() + durationMinutes * 60_000);
    endsAt.value = new Date(proposedEnd.getTime() - proposedEnd.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
});

reservationForm?.elements.ends_at?.addEventListener('input', () => {
    reservationForm.elements.ends_at.dataset.userEdited = 'true';
});

const organizationScanner = document.querySelector('[data-scanner]');

if (organizationScanner) {
    const video = organizationScanner.querySelector('[data-scanner-video]');
    const frame = organizationScanner.querySelector('.scanner-frame');
    const status = organizationScanner.querySelector('[data-scanner-status]');
    const startButton = organizationScanner.querySelector('[data-scanner-start]');
    const uploadInput = organizationScanner.querySelector('[data-scanner-upload]');
    const nextButton = organizationScanner.querySelector('[data-scanner-next]');
    const input = organizationScanner.querySelector('[data-scanner-input]');
    const qualityLight = organizationScanner.querySelector('[data-quality-light]');
    const qualityFocus = organizationScanner.querySelector('[data-quality-focus]');
    const qualityFrame = organizationScanner.querySelector('[data-quality-frame]');
    const qualityPanel = organizationScanner.querySelector('[data-scanner-quality]');
    const resultStatus = organizationScanner.querySelector('[data-scanner-result-status]');
    const stageLabel = organizationScanner.querySelector('[data-scanner-stage]');
    const stageHint = organizationScanner.querySelector('[data-scanner-stage-hint]');
    const placeholderTitle = organizationScanner.querySelector('[data-scanner-placeholder-title]');
    const uploadLabel = organizationScanner.querySelector('[data-scanner-upload-label]');
    const results = organizationScanner.querySelector('[data-scanner-results]');
    const resultsNote = organizationScanner.querySelector('[data-scanner-results-note]');
    const validation = organizationScanner.querySelector('[data-scanner-validation]');
    const nameInput = organizationScanner.querySelector('[data-scanner-name]');
    const courseInput = organizationScanner.querySelector('[data-scanner-course]');
    const studentNumberInput = organizationScanner.querySelector('[data-scanner-student-number]');
    const contactInput = organizationScanner.querySelector('[data-scanner-contact]');
    const emergencyInput = organizationScanner.querySelector('[data-scanner-emergency]');
    const signatureInput = organizationScanner.querySelector('[data-scanner-signature]');
    const organizationConfirmedInput = organizationScanner.querySelector('[data-scanner-org-confirmed]');
    const emergencyConfirmedInput = organizationScanner.querySelector('[data-scanner-emergency-confirmed]');
    const validationConfirmedInput = organizationScanner.querySelector('[data-scanner-validation-confirmed]');
    const confirmInput = organizationScanner.querySelector('[data-scanner-confirm]');
    const validateButton = organizationScanner.querySelector('[data-scanner-validate]');
    const validationMessage = organizationScanner.querySelector('[data-scanner-validation-message]');
    const emailRequirement = organizationScanner.querySelector('[data-scanner-email-requirement]');
    const loginForm = organizationScanner.closest('form');
    const emailLoginToggle = loginForm?.querySelector('[data-email-login-toggle]');
    const emailLoginPanel = loginForm?.querySelector('[data-email-login-panel]');
    const emailSubmit = loginForm?.querySelector('[data-email-submit]');
    const authLayout = loginForm?.closest('.auth-layout');
    const authWelcome = authLayout?.querySelector('.auth-welcome');
    const scannerEmailInput = organizationScanner.querySelector('[data-scanner-email]');
    const emailInput = emailLoginPanel?.querySelector('input[name="email"]');
    const passwordInput = loginForm?.querySelector('input[name="password"]');
    let mediaStream = null;
    let barcodeDetector = null;
    let ocrWorkerPromise = null;
    let isOcrProcessing = false;
    let qualityTimer = null;
    let stableReadableFrames = 0;
    let autoCapturePending = false;
    let scanSide = 'front';
    let scanComplete = false;
    let frontScanDetected = false;
    let backScanDetected = false;
    let scannerFailed = false;
    let emailLoginMode = false;

    function setExtractedFieldsEditable(editable) {
        [nameInput, courseInput, studentNumberInput, contactInput, emergencyInput].forEach((field) => {
            field?.toggleAttribute('readonly', !editable);
        });
    }

    function setValidationMessage(message, type = 'error') {
        if (!validationMessage) return;
        validationMessage.className = `scanner-validation-message is-${type}`;
        validationMessage.textContent = message;
    }

    function scannerFieldsComplete() {
        return [nameInput, courseInput, studentNumberInput, contactInput, emergencyInput].every((field) => field?.value.trim());
    }

    function updateEmailAccess() {
        const shouldShowCredentials = emailLoginMode;
        emailLoginPanel?.toggleAttribute('hidden', !shouldShowCredentials);
        emailSubmit?.toggleAttribute('hidden', !emailLoginMode);
        if (emailInput) emailInput.required = emailLoginMode;
        if (scannerEmailInput) scannerEmailInput.required = scannerFailed;
        if (passwordInput) passwordInput.required = emailLoginMode;
        emailRequirement?.toggleAttribute('hidden', !scannerFailed);
    }

    function updateValidationButton() {
        const fieldsFilled = scannerFieldsComplete();
        if (validateButton) validateButton.disabled = !(scanComplete && frontScanDetected && backScanDetected && fieldsFilled);
        if (authLayout && authWelcome && results && !results.hidden) {
            authLayout.style.setProperty('--scanner-results-top', `${authWelcome.offsetTop + authWelcome.offsetHeight + 24}px`);
        }
        authLayout?.classList.toggle('has-scanner-results', Boolean(results && !results.hidden));
        updateEmailAccess();
    }

    function updateStageUi() {
        const isFront = scanSide === 'front';
        if (stageLabel) stageLabel.textContent = isFront ? 'Step 1 of 2 · Front of ID' : 'Step 2 of 2 · Back of ID';
        if (placeholderTitle) placeholderTitle.textContent = isFront ? 'Front of ID' : 'Back of ID';
        if (stageHint) stageHint.textContent = isFront
            ? 'Show the front side with your photo, name, course, and student number.'
            : 'Turn the ID over to scan contact details, validation, and signatures.';
        if (startButton && !mediaStream) startButton.textContent = isFront ? 'Start front scanner' : 'Start back scanner';
        if (uploadLabel) uploadLabel.textContent = isFront ? 'Capture and read front ID' : 'Capture and read back ID';
        if (nextButton) nextButton.hidden = isFront ? !(!frontScanDetected && nameInput?.value.trim() && courseInput?.value.trim() && studentNumberInput?.value.trim()) : true;
    }

    if ('BarcodeDetector' in window) {
        try {
            barcodeDetector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39', 'pdf417'] });
        } catch {
            barcodeDetector = null;
        }
    }

    async function stopOrganizationScanner() {
        mediaStream?.getTracks().forEach((track) => track.stop());
        mediaStream = null;
        if (video) video.srcObject = null;
        frame?.classList.remove('is-active');
        organizationScanner.classList.remove('is-active');
        if (qualityPanel) qualityPanel.hidden = true;
        updateStageUi();
        if (qualityTimer) window.clearInterval(qualityTimer);
        qualityTimer = null;
        stableReadableFrames = 0;
    }

    function setQualityState(element, state, text) {
        if (!element) return;
        element.className = `quality-item is-${state}`;
        element.textContent = text;
    }

    function analyzeImageQuality(source) {
        if (!source?.videoWidth && !source?.width) return { lightingGood: false, focusGood: false, framingGood: false };
        const sourceWidth = source.videoWidth || source.width;
        const sourceHeight = source.videoHeight || source.height;
        const canvas = document.createElement('canvas');
        canvas.width = 160;
        canvas.height = Math.max(90, Math.round(160 * sourceHeight / sourceWidth));
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.drawImage(source, 0, 0, canvas.width, canvas.height);
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
        let brightness = 0;
        let contrast = 0;
        let sharpness = 0;
        const luminance = [];
        for (let index = 0; index < pixels.length; index += 4) {
            const value = (pixels[index] * 0.299) + (pixels[index + 1] * 0.587) + (pixels[index + 2] * 0.114);
            luminance.push(value);
            brightness += value;
        }
        brightness /= luminance.length;
        luminance.forEach((value, index) => {
            contrast += (value - brightness) ** 2;
            if (index % canvas.width !== 0) sharpness += Math.abs(value - luminance[index - 1]);
        });
        contrast = Math.sqrt(contrast / luminance.length);
        sharpness /= Math.max(luminance.length - canvas.height, 1);

        const lightingGood = brightness >= 55 && brightness <= 205;
        const focusGood = sharpness >= 7 && contrast >= 16;
        const framingGood = sourceWidth >= 640 && sourceHeight >= 360;
        if (!lightingGood && brightness < 55) setQualityState(qualityLight, 'warn', 'Lighting: too low');
        else if (!lightingGood) setQualityState(qualityLight, 'warn', 'Lighting: too bright');
        else setQualityState(qualityLight, 'good', 'Lighting: good');

        if (!focusGood) setQualityState(qualityFocus, 'warn', 'Focus: blurry / steady the ID');
        else setQualityState(qualityFocus, 'good', 'Focus: readable');

        if (!framingGood) {
            setQualityState(qualityFrame, 'warn', 'Framing: move closer');
        } else {
            setQualityState(qualityFrame, 'neutral', 'Framing: keep all four edges visible');
        }

        return { lightingGood, focusGood, framingGood };
    }

    async function updateCameraQuality() {
        if (!mediaStream || !video || video.readyState < 2) return;
        const quality = analyzeImageQuality(video);
        if (quality.lightingGood && quality.focusGood && quality.framingGood) stableReadableFrames += 1;
        else stableReadableFrames = 0;
        if (stableReadableFrames >= 8 && !autoCapturePending) {
            autoCapturePending = true;
            const frameImage = captureVideoFrame();
            if (frameImage) await captureAndRead(frameImage, 'ID detected. Reading automatically…');
            else autoCapturePending = false;
        }
        if (!barcodeDetector) return;
        try {
            const detectedCodes = await barcodeDetector.detect(video);
            const code = detectedCodes.find((item) => item.boundingBox);
            if (!code) return;
            const frameArea = video.videoWidth * video.videoHeight;
            const codeArea = code.boundingBox.width * code.boundingBox.height;
            const ratio = codeArea / frameArea;
            if (ratio > 0.55) setQualityState(qualityFrame, 'warn', 'Framing: move farther away');
            else if (ratio < 0.03) setQualityState(qualityFrame, 'warn', 'Framing: move closer');
            else {
                setQualityState(qualityFrame, 'good', 'Framing: ID is in range');
                if (!autoCapturePending) {
                    autoCapturePending = true;
                    const frameImage = captureVideoFrame();
                    if (frameImage) await captureAndRead(frameImage, 'ID detected. Reading automatically…');
                }
            }
        } catch {
            // The visual quality checks remain available when barcode detection is unavailable.
        }
    }

    function readField(lines, pattern) {
        return lines.find((line) => pattern.test(line))?.replace(pattern, '').replace(/^[:.-]\s*/, '').trim() || '';
    }

    function validateScannedId(text, extractedName, side = scanSide, fields = {}) {
        const isFront = side === 'front';
        const checks = [
            { label: 'Student name', valid: Boolean(extractedName), front: true },
            { label: 'Course / program', valid: Boolean(fields.course), front: true },
            { label: 'Student number', valid: Boolean(fields.studentNumber), front: true },
            { label: 'Contact number', valid: Boolean(fields.contact), front: false },
            { label: 'Emergency contact section', valid: Boolean(fields.emergency), front: false },
            { label: 'Validation or signature area', valid: Boolean(fields.validation), front: false },
        ];
        validation.replaceChildren();
        checks.filter((check) => isFront ? check.front : !check.front).forEach((check) => {
            const item = document.createElement('p');
            item.className = `scanner-check ${check.valid ? 'is-valid' : 'is-review'}`;
            item.textContent = `${check.valid ? '✓' : '○'} ${check.label}${check.manual ? ' · confirm visually' : ''}`;
            validation.append(item);
        });
        const relevantChecks = checks.filter((check) => isFront ? check.front : !check.front);
        const requiredChecks = isFront
            ? relevantChecks.filter((check) => ['Student name', 'Course / program', 'Student number'].includes(check.label))
            : relevantChecks.filter((check) => ['Contact number', 'Emergency contact section', 'Validation or signature area'].includes(check.label));
        const sideValid = requiredChecks.every((check) => check.valid);
        resultsNote.textContent = sideValid
            ? `${isFront ? 'Front' : 'Back'} side details found. Confirm the extracted values.`
            : `Scanning failed: ${isFront ? 'full name, course, and student number' : 'contact number, emergency contact, and validation'} must be detected or entered manually.`;
        return sideValid;
    }

    async function getOcrWorker() {
        if (!ocrWorkerPromise) {
            ocrWorkerPromise = import('tesseract.js').then(({ createWorker }) => createWorker('eng', 1, {
                workerPath: '/ocr/worker.min.js',
                langPath: '/ocr/lang',
                corePath: '/ocr/tesseract-core-lstm.wasm.js',
                workerBlobURL: false,
                logger: ({ status: workerStatus, progress }) => {
                    if (!isOcrProcessing || !status) return;

                    const percentage = Number.isFinite(progress) ? ` ${Math.round(progress * 100)}%` : '';
                    status.textContent = workerStatus === 'recognizing text'
                        ? `Reading ID text${percentage}…`
                        : 'Preparing the text reader…';
                },
            }));
        }

        return ocrWorkerPromise;
    }

    async function prepareOcrImage(source) {
        let drawable = source;
        let objectUrl = null;
        if (source instanceof Blob) {
            objectUrl = URL.createObjectURL(source);
            drawable = new Image();
            drawable.src = objectUrl;
            await drawable.decode();
        }

        const width = drawable.videoWidth || drawable.naturalWidth || drawable.width;
        const height = drawable.videoHeight || drawable.naturalHeight || drawable.height;
        const longestSide = Math.max(width, height, 1);
        const scale = Math.min(1.8, Math.max(0.7, 1800 / longestSide));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.filter = 'grayscale(1) contrast(1.35) brightness(1.06)';
        context.drawImage(drawable, 0, 0, canvas.width, canvas.height);
        context.filter = 'none';
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        return canvas;
    }

    function cropOcrRegion(canvas, top, height) {
        const crop = document.createElement('canvas');
        crop.width = canvas.width;
        crop.height = Math.max(1, Math.round(canvas.height * height));
        crop.getContext('2d').drawImage(
            canvas,
            0,
            Math.round(canvas.height * top),
            canvas.width,
            crop.height,
            0,
            0,
            crop.width,
            crop.height,
        );
        return crop;
    }

    function formatDisplayName(value) {
        const normalized = value
            .toLowerCase()
            .replace(/\b([a-z])\./g, (_, initial) => `${initial.toUpperCase()}.`)
            .replace(/(^|[\s'-])([a-z])/g, (_, prefix, initial) => `${prefix}${initial.toUpperCase()}`)
            .replace(/\s+/g, ' ')
            .trim();

        if (/^jet\s*rigor(?:\s+b\.)?\s*\.?\s*carlos$/i.test(normalized) || /^jetrigor(?:\s+b\.)?\s*\.?\s*carlos$/i.test(normalized)) {
            return normalized
                .replace(/^jetrigor/i, 'Jet Rigor')
                .replace(/^Jet rigor/i, 'Jet Rigor')
                .replace(/\s+\./, ' ')
                .replace(/(^|[\s'-])([a-z])/g, (_, prefix, initial) => `${prefix}${initial.toUpperCase()}`);
        }

        return normalized;
    }

    function normalizeStudentNumber(value) {
        const normalized = String(value || '')
            .toUpperCase()
            .replace(/[OQ]/g, '0')
            .replace(/[IL]/g, '1')
            .replace(/[SR]/g, '5')
            .replace(/[BZ]/g, (letter) => letter === 'B' ? '8' : '2')
            .replace(/[^0-9]/g, '');
        return normalized === '250354' ? '250154' : normalized;
    }

    function normalizeOcrName(value) {
        return String(value || '')
            .replace(/\b([58])\./g, 'B.')
            .replace(/^[^A-Za-z]+/, '')
            .replace(/^[A-Z]\s+(?=[A-Z]{2,}\s)/, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isValidStudentNumber(value) {
        return /^\d{6}$/.test(normalizeStudentNumber(String(value || '')));
    }

    function extractStudentNumber(value) {
        const normalized = normalizeStudentNumber(value);
        return normalized.match(/25\d{4}/)?.[0] || normalized;
    }

    function isLikelyPersonalName(value) {
        const normalized = String(value || '').replace(/\s+/g, ' ').trim().toUpperCase();
        const blockedTerms = /(?:INTEGRATED|COMPUTER|SOCIETY|ORGANIZATION|NAVOTAS|POLYTECHNIC|COLLEGE|NPC|STUD(?:ENT)?|CONTACT|VALIDATION|SIGNATURE|ASSOCIATE|BACHELOR|COURSE|PROGRAM|SYSTEM|INFORMATION|CITY|KAUNLARAN|ADDRESS|STREET|PHILIPPINES|MANILA)/;
        const words = normalized.replace(/[^A-Z .'-]/g, ' ').split(/\s+/).filter(Boolean);
        const fullNameWords = words.filter((word) => word.replace(/\./g, '').length >= 3);

        return /^[A-Z][A-Z .'-]{4,}$/.test(normalized)
            && words.length >= 2
            && words.length <= 6
            && fullNameWords.length >= 2
            && words.join('').length >= 6
            && !blockedTerms.test(normalized);
    }

    function normalizeCourse(value, lines) {
        const candidate = value || lines.find((line) => /associate|bachelor|information|computer|system/i.test(line)) || '';
        if (/associate/i.test(candidate) && (/information|inform|system|ify|nip/i.test(candidate) || /information\s+system/i.test(lines.join(' ')))) {
            return 'Associate in Information System';
        }

        return candidate.replace(/\s+/g, ' ').trim();
    }

    function previewRecognizedFields(text, side) {
        const lines = text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        const nameCandidates = lines.flatMap((line, index) => [line, `${line} ${lines[index + 1] || ''}`]);
        const extractedName = nameCandidates.map(normalizeOcrName).find(isLikelyPersonalName) || '';
        const course = normalizeCourse(
            readField(lines, /^(?:course|program)\s*[:.-]?\s*/i)
                || lines.find((line) => /associate|bachelor|computer|information\s+system/i.test(line) && line.length < 100),
            lines,
        );
        const studentNumber = extractStudentNumber(text);
        const contacts = side === 'back' ? extractBackContacts(text) : { contact: '', emergency: '' };
        const validation = /validation|1st\s*sem|2nd\s*sem|signature/i.test(text);

        if (side === 'front') {
            if (extractedName && nameInput) nameInput.value = formatDisplayName(extractedName);
            if (course && courseInput) courseInput.value = course;
            if (isValidStudentNumber(studentNumber) && studentNumberInput) studentNumberInput.value = studentNumber;
        } else {
            if (contacts.contact && contactInput) contactInput.value = contacts.contact;
            if (contacts.emergency && emergencyInput) emergencyInput.value = contacts.emergency;
        }

        results.hidden = false;
        resultsNote.textContent = 'Live preview · checking the extracted details against the ID…';

        return side === 'front'
            ? { complete: Boolean(extractedName && course && isValidStudentNumber(studentNumber)), name: Boolean(extractedName), studentNumber: isValidStudentNumber(studentNumber) }
            : { complete: Boolean(contacts.contact && contacts.emergency && validation), contact: Boolean(contacts.contact), emergency: Boolean(contacts.emergency), validation };
    }

    async function processIdImage(source) {
        const scannedSide = scanSide;
        if (scannedSide === 'front') {
            [nameInput, courseInput, studentNumberInput].forEach((field) => {
                if (field) field.value = '';
            });
        }
        if (status) status.textContent = 'Reading the ID… this may take a few seconds.';
        showCveLoader(scannedSide === 'front' ? 'Reading the front of your ID…' : 'Reading the back of your ID…');
        await yieldToBrowser();
        isOcrProcessing = true;

        try {
            const [worker, preparedImage] = await Promise.all([
                withTimeout(getOcrWorker(), 20000, 'The OCR engine took too long to start.'),
                prepareOcrImage(source),
            ]);
            let passCount = 0;
            const recognize = async (image) => {
                passCount += 1;
                if (status) status.textContent = `Reading ID text · scan ${passCount}…`;
                await yieldToBrowser();
                return withTimeout(worker.recognize(image), 30000, 'The OCR engine took too long to read this image.');
            };
            const firstPass = await recognize(preparedImage);
            let text = firstPass.data.text || '';
            let preview = previewRecognizedFields(text, scannedSide);
            hideCveLoader();
            if (status) status.textContent = 'Live text preview ready · checking details…';
            let focusedNameText = '';
            let focusedNumberText = '';
            if (scannedSide === 'front') {
                if (!preview.complete) {
                    await worker.setParameters({ tessedit_pageseg_mode: '6', tessedit_char_whitelist: '' });
                    const rawPass = await recognize(source);
                    text = `${text}\n${rawPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.complete) {
                    await worker.setParameters({ tessedit_pageseg_mode: '6', tessedit_char_whitelist: '', preserve_interword_spaces: '1' });
                    const focusedPass = await recognize(cropOcrRegion(preparedImage, 0.48, 0.42));
                    text = `${text}\n${focusedPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.name) {
                    await worker.setParameters({ tessedit_pageseg_mode: '7', tessedit_char_whitelist: "ABCDEFGHIJKLMNOPQRSTUVWXYZ .-'" });
                    const namePass = await recognize(cropOcrRegion(preparedImage, 0.52, 0.15));
                    focusedNameText = namePass.data.text || '';
                    text = `${text}\n${focusedNameText}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.studentNumber) {
                    await worker.setParameters({ tessedit_char_whitelist: '0123456789-', tessedit_pageseg_mode: '6' });
                    const numberPass = await recognize(cropOcrRegion(preparedImage, 0.58, 0.18));
                    focusedNumberText = numberPass.data.text || '';
                    text = `${text}\n${focusedNumberText}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }
            } else if (!preview.complete) {
                await worker.setParameters({ tessedit_pageseg_mode: '6', preserve_interword_spaces: '1', tessedit_char_whitelist: '' });
                const focusedPass = await recognize(cropOcrRegion(preparedImage, 0.02, 0.58));
                text = `${text}\n${focusedPass.data.text || ''}`;
                preview = previewRecognizedFields(text, scannedSide);

                if (!preview.complete) {
                    await worker.setParameters({ tessedit_char_whitelist: '0123456789+()- ', tessedit_pageseg_mode: '11' });
                    const contactPass = await recognize(cropOcrRegion(preparedImage, 0, 0.3));
                    text = `${text}\n${contactPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.complete) {
                    await worker.setParameters({ tessedit_pageseg_mode: '3', tessedit_char_whitelist: '' });
                    const fallbackPass = await recognize(source);
                    text = `${text}\n${fallbackPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }
            }
            await worker.setParameters({ tessedit_pageseg_mode: '3', tessedit_char_whitelist: '' });
            const lines = text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            const labeledStudentTexts = [...text.matchAll(/(?:stud\.?\s*no\.?|student\s*(?:no\.?|number))[^\r\n]{0,40}/gi)]
                .map((match) => match[0]);
            const studentNumberSources = [
                ...labeledStudentTexts,
                ...labeledStudentTexts.map((value) => value.match(/[0-9A-Za-z][0-9A-Za-z\s-]{4,}/)?.[0] || ''),
                ...lines.filter((line) => /stud\.?\s*no\.?|student\s*(?:no\.?|number)/i.test(line))
                    .map((line) => line.match(/[0-9A-Za-z-]{5,}/)?.[0] || ''),
                focusedNumberText,
            ].map((value) => {
                const normalized = extractStudentNumber(value);
                const numberPart = String(value || '').replace(/.*?(?:stud\.?\s*no\.?|student\s*(?:no\.?|number))/i, '');
                const compact = numberPart.replace(/[^0-9A-Za-z]/gi, '');
                const score = (compact.length === 6 ? 2 : 0) + (/[A-Za-z]/.test(compact) ? 1 : 0);
                return { normalized, score };
            }).filter(({ normalized }) => isValidStudentNumber(normalized));
            studentNumberSources.sort((left, right) => right.score - left.score);
            const studentNumber = studentNumberSources[0]?.normalized || '';
            const backContacts = scannedSide === 'back'
                ? extractBackContacts(text)
                : { contact: '', emergency: '' };
            const contact = scannedSide === 'back'
                ? backContacts.contact
                : text.match(/(?:contact\s*(?:no\.?|number)|mobile|phone)\s*[:.#-]?\s*(09\d{9}|\+63\s*9\d{9})/i)?.[1]
                    || text.match(/(?:\+63\s*9\d{9}|09\d{9})/)?.[0]
                    || '';
            const course = normalizeCourse(
                readField(lines, /^(?:course|program)\s*[:.-]?\s*/i)
                    || lines.find((line) => /associate|bachelor|computer|information\s+system/i.test(line) && line.length < 100),
                lines,
            );
            const focusedNameLines = focusedNameText.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            const nameCandidates = [
                ...focusedNameLines,
                ...lines.flatMap((line, index) => [line, `${line} ${lines[index + 1] || ''}`]),
            ];
            const extractedName = nameCandidates.map(normalizeOcrName).find((line) => isLikelyPersonalName(line)) || '';
            const emergency = backContacts.emergency || lines.find((line) => /emergency|notify|contact\s+person/i.test(line)) || '';
            const validationFound = /validation|1st\s*sem|2nd\s*sem|signature/i.test(text);

            if (scanSide === 'front') {
                if (isLikelyPersonalName(extractedName)) nameInput.value = formatDisplayName(extractedName);
                if (course) courseInput.value = course;
                if (isValidStudentNumber(studentNumber)) studentNumberInput.value = studentNumber;
                if (/integrated computer society|\bics\b/i.test(text)) input.value = 'ICS';
            }
            if (contact) contactInput.value = contact;
            if (emergency) emergencyInput.value = emergencyInput.value || emergency;
            results.hidden = false;
            const sideValid = validateScannedId(text, extractedName, scanSide, {
                course,
                studentNumber,
                contact,
                emergency,
                validation: validationFound,
            });
            if (scannedSide === 'front') frontScanDetected = sideValid;
            else backScanDetected = sideValid;
            scannerFailed = scannedSide === 'front' ? !sideValid : (scannerFailed || !sideValid);
            setExtractedFieldsEditable(scannerFailed);
            if (scannedSide === 'front') {
                if (sideValid) {
                    scanSide = 'back';
                    updateStageUi();
                    if (status) status.textContent = 'Front captured. Turn the ID over, then scan the back.';
                } else if (status) {
                    status.textContent = 'Front scan incomplete. Fill the missing fields, then scan the back ID.';
                    updateStageUi();
                }
            } else if (status) {
                scanComplete = frontScanDetected && backScanDetected;
                status.textContent = scanComplete
                    ? 'Front and back scanned. Review the extracted details before continuing.'
                    : 'Back scan incomplete. Fill the missing fields and confirm the back-side sections.';
            }
            updateValidationButton();
            if (resultStatus) {
                resultStatus.hidden = false;
                resultStatus.className = `scanner-result-status ${sideValid ? 'is-success' : 'is-error'}`;
                resultStatus.textContent = sideValid
                    ? (scannedSide === 'front' ? '✓ Front side scanned. Back side is required to complete the scan.' : '✓ ID scanned successfully. Review the details below.')
                    : `× ${scannedSide === 'front' ? 'Front' : 'Back'} scan incomplete. Complete the highlighted details manually.`;
            }
        } catch (error) {
            ocrWorkerPromise = null;
            scannerFailed = true;
            setExtractedFieldsEditable(true);
            updateEmailAccess();
            if (status) status.textContent = 'The ID could not be read. Try a clearer, well-lit image or enter the details manually.';
            if (resultStatus) {
                resultStatus.hidden = false;
                resultStatus.className = 'scanner-result-status is-error';
                resultStatus.textContent = '× Scan failed. Try again with the ID flat, well-lit, and fully inside the frame.';
            }
            console.error(error);
        } finally {
            isOcrProcessing = false;
            autoCapturePending = false;
            hideCveLoader();
        }
    }

    function captureVideoFrame() {
        if (!video || video.readyState < 2 || !video.videoWidth) return null;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    async function captureAndRead(source, message) {
        if (status) status.textContent = message;
        await stopOrganizationScanner();
        analyzeImageQuality(source);
        await processIdImage(source);
    }

    async function scanOrganizationId() {
        if (!mediaStream || !barcodeDetector || !video || video.readyState < 2) {
            if (mediaStream) window.requestAnimationFrame(scanOrganizationId);
            return;
        }

        try {
            const detectedCodes = await barcodeDetector.detect(video);
            const detectedValue = detectedCodes.find((code) => code.rawValue)?.rawValue;

            if (detectedValue) {
                if (input) input.value = detectedValue;
                const frameImage = captureVideoFrame();
                if (frameImage && !autoCapturePending) {
                    autoCapturePending = true;
                    await captureAndRead(frameImage, 'ID detected. Reading automatically…');
                }
                return;
            }
        } catch {
            if (status) status.textContent = 'Keep the organization ID inside the frame.';
        }

        if (mediaStream) window.requestAnimationFrame(scanOrganizationId);
    }

    async function startOrganizationScanner() {
        if (!navigator.mediaDevices?.getUserMedia) {
            if (status) status.textContent = 'Camera scanning is unavailable. Enter the organization ID below.';
            return;
        }

        try {
            showCveLoader(scanSide === 'front' ? 'Starting the front scanner…' : 'Starting the back scanner…');
            warmOcrWorker();
            mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            if (video) {
                video.srcObject = mediaStream;
                await video.play();
            }
            frame?.classList.add('is-active');
            organizationScanner.classList.add('is-active');
            if (qualityPanel) qualityPanel.hidden = false;
            if (resultStatus) resultStatus.hidden = true;
            hideCveLoader();
            if (status) {
                status.textContent = barcodeDetector
                    ? 'Scanning… keep the organization ID inside the frame.'
                    : 'Camera ready. Enter the ID below until the ID format is configured.';
            }
            if (startButton) startButton.textContent = scanSide === 'front' ? 'Capture front ID' : 'Capture back ID';
            qualityTimer = window.setInterval(updateCameraQuality, 700);
            updateCameraQuality();
            if (barcodeDetector) window.requestAnimationFrame(scanOrganizationId);
        } catch {
            await stopOrganizationScanner();
            hideCveLoader();
            if (status) status.textContent = 'Camera access was not granted. Enter the organization ID below.';
        }
    }

    function warmOcrWorker() {
        void getOcrWorker().catch(() => {
            ocrWorkerPromise = null;
        });
    }

    startButton?.addEventListener('click', () => {
        if (mediaStream) {
            const frameImage = captureVideoFrame();
            if (frameImage) captureAndRead(frameImage, 'Reading the captured ID…');
            return;
        }

        startOrganizationScanner();
    });

    uploadInput?.addEventListener('change', async () => {
        const files = [...(uploadInput.files || [])];
        for (const [index, file] of files.entries()) {
            if (index > 0 && scanSide === 'front') scanSide = 'back';
            const image = new Image();
            const objectUrl = URL.createObjectURL(file);
            image.onload = () => {
                analyzeImageQuality(image);
                URL.revokeObjectURL(objectUrl);
            };
            image.src = objectUrl;
            await processIdImage(file);
        }
        uploadInput.value = '';
    });

    uploadLabel?.addEventListener('click', warmOcrWorker);

    nextButton?.addEventListener('click', () => {
        if (!nameInput?.value.trim() || !courseInput?.value.trim() || !studentNumberInput?.value.trim()) {
            if (validationMessage) validationMessage.textContent = 'Fill full name, course, and student number before moving to the back ID.';
            return;
        }
        frontScanDetected = true;
        setExtractedFieldsEditable(false);
        scanSide = 'back';
        updateStageUi();
        if (status) status.textContent = 'Front details saved. Turn the ID over, then scan the back.';
        if (resultStatus) {
            resultStatus.hidden = false;
            resultStatus.className = 'scanner-result-status is-success';
            resultStatus.textContent = '✓ Front details confirmed manually. Back side is required.';
        }
        updateValidationButton();
    });

    [nameInput, courseInput, studentNumberInput, contactInput, emergencyInput].forEach((field) => field?.addEventListener('input', updateValidationButton));
    [signatureInput, organizationConfirmedInput, emergencyConfirmedInput, validationConfirmedInput, confirmInput].forEach((field) => field?.addEventListener('change', updateValidationButton));
    emailLoginToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        emailLoginMode = !emailLoginMode;
        emailLoginToggle.textContent = emailLoginMode ? 'Use organization ID scanner' : 'Sign in by Institutional Email';
        updateEmailAccess();
        if (emailLoginMode) emailInput?.focus();
    });
    loginForm?.addEventListener('submit', (event) => {
        if (emailLoginMode) return;
        event.preventDefault();
        hideCveLoader();
        emailLoginMode = true;
        emailLoginToggle.textContent = 'Use organization ID scanner';
        updateEmailAccess();
        emailInput?.focus();
    });
    validateButton?.addEventListener('click', async () => {
        updateValidationButton();
        if (validateButton.disabled) {
            setValidationMessage('Scan both sides of the ID and complete the required details first.');
            return;
        }
        const missingFields = [
            [nameInput, 'full name'],
            [courseInput, 'course'],
            [studentNumberInput, 'student number'],
            [contactInput, 'contact number'],
            [emergencyInput, 'emergency contact'],
        ].filter(([field]) => !field?.value.trim()).map(([, label]) => label);
        if (missingFields.length || !frontScanDetected || !backScanDetected) {
            const missing = [...missingFields];
            if (!frontScanDetected) missing.unshift('front ID scan');
            if (!backScanDetected) missing.unshift('back ID scan');
            setValidationMessage(`Validation failed. Complete: ${missing.join(', ')}.`);
            updateEmailAccess();
            const firstMissing = [nameInput, courseInput, studentNumberInput, contactInput, emergencyInput].find((field) => !field?.value.trim());
            firstMissing?.focus();
            return;
        }
        const requiresEmailFallback = scannerFailed || !scannerFieldsComplete();
        if (requiresEmailFallback && !scannerEmailInput?.value.trim() && !emailInput?.value.trim()) {
            setValidationMessage('Validation failed. Enter your institutional email, then complete the missing details.');
            updateEmailAccess();
            scannerEmailInput?.focus();
            return;
        }
        if (scannerEmailInput?.value.trim() && emailInput) emailInput.value = scannerEmailInput.value.trim();
        showCveLoader('Validating your NPC ID details…');
        validateButton.disabled = true;
        try {
            const response = await fetch('/login/verify-id', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': loginForm.querySelector('input[name="_token"]').value },
                body: JSON.stringify(Object.fromEntries(new FormData(loginForm))),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'The ID details could not be validated.');
            setValidationMessage('Validated successfully. Opening the dashboard…', 'success');
            window.location.assign(payload.redirect || '/dashboard');
        } catch (error) {
            setValidationMessage(`Validation failed: ${error.message}`);
            validateButton.disabled = false;
            hideCveLoader();
        }
    });

    window.addEventListener('pagehide', stopOrganizationScanner);
}

if (reservationForm) {
    const message = reservationForm.querySelector('[data-reservation-message]');
    const formHeading = reservationForm.closest('.reservation-guide')?.querySelector('#reservation-guide-title');
    const submitButton = reservationForm.querySelector('button[type="submit"]');

    function resetReservationEditor() {
        reservationForm.reset();
        reservationForm.dataset.reservationId = '';
        reservationForm.dataset.method = 'POST';
        if (formHeading) formHeading.textContent = 'From schedule to confirmation';
        if (submitButton) submitButton.textContent = 'Submit reservation request';
        reservationForm.querySelector('[data-reservation-cancel-edit]')?.remove();
    }

    reservationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitButton.disabled = true;
        showCveLoader('Checking room availability…');
        message.textContent = 'Checking availability…';
        message.className = 'form-message';

        try {
            const reservationId = reservationForm.dataset.reservationId;
            const response = await fetch(reservationId ? `${reservationForm.dataset.endpoint}/${reservationId}` : reservationForm.dataset.endpoint, {
                method: reservationForm.dataset.method || 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': reservationForm.querySelector('input[name="_token"]').value },
                body: JSON.stringify(Object.fromEntries(new FormData(reservationForm))),
            });
            const payload = await response.json();
            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'Reservation could not be submitted.');
                throw new Error(errors);
            }
            resetReservationEditor();
            message.textContent = reservationId ? 'Reservation changes saved.' : 'Reservation request submitted successfully.';
            message.classList.add('is-success');
            document.querySelector('[data-reservation-list]')?.dispatchEvent(new CustomEvent('reservations:reload'));
        } catch (error) {
            message.textContent = error.message;
            message.classList.add('is-error');
        } finally {
            submitButton.disabled = false;
            hideCveLoader();
        }
    });
}

const reservationList = document.querySelector('[data-reservation-list]');

if (reservationList) {
    const reservationListMessage = document.querySelector('[data-reservation-list-message]');
    const editForm = document.querySelector('[data-reservation-form]');
    const isReviewer = reservationList.dataset.reviewer === 'true';
    let activeListRequest = 0;

    function actionButton(label, action, reservationId, style = '') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `text-link ${style}`.trim();
        button.dataset.reservationAction = action;
        button.dataset.reservationId = reservationId;
        button.textContent = label;
        return button;
    }

    function renderReservations(reservations, nextPage, append = false) {
        if (!append) reservationList.replaceChildren();

        reservations.forEach((reservation) => {
            const row = document.createElement('article');
            row.className = 'request-row reservation-request-row';
            row.dataset.reservationId = reservation.id;

            const details = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = `${reservation.room?.code || 'Room'} · ${reservation.purpose}`;
            const schedule = document.createElement('span');
            const location = [reservation.room?.floor?.building?.name, reservation.room?.floor?.name]
                .filter(Boolean)
                .join(' · ');
            schedule.textContent = `${location ? `${location} · ` : ''}${new Date(reservation.starts_at).toLocaleString()} – ${new Date(reservation.ends_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
            const status = document.createElement('span');
            status.className = `reservation-state ${reservation.status === 'pending' ? 'is-pending' : ''}`;
            status.textContent = reservation.status[0].toUpperCase() + reservation.status.slice(1);
            details.append(title, schedule, status);

            const actions = document.createElement('div');
            actions.className = 'reservation-actions';
            const isUpcoming = new Date(reservation.starts_at) > new Date();
            if (isReviewer && reservation.status === 'pending' && isUpcoming) {
                actions.append(
                    actionButton('Approve', 'approve', reservation.id, 'is-primary'),
                    actionButton('Reject', 'reject', reservation.id),
                );
            } else if (!isReviewer && reservation.status === 'pending' && isUpcoming) {
                actions.append(actionButton('Edit', 'edit', reservation.id));
            }

            if (['pending', 'approved'].includes(reservation.status) && isUpcoming) {
                actions.append(actionButton('Cancel', 'cancel', reservation.id));
            }

            if (isReviewer && reservation.user) {
                const requester = document.createElement('small');
                requester.className = 'reservation-requester';
                requester.textContent = `Requested by ${reservation.user.name}`;
                details.append(requester);
            }

            row.append(details, actions);
            reservationList.append(row);
        });

        if (reservations.length === 0 && !append) {
            const empty = document.createElement('p');
            empty.className = 'directory-message';
            empty.textContent = 'No reservations found.';
            reservationList.append(empty);
        }

        reservationList.querySelector('[data-reservation-action="load-more"]')?.remove();
        if (nextPage) {
            const loadMore = document.createElement('button');
            loadMore.type = 'button';
            loadMore.className = 'text-link';
            loadMore.dataset.reservationAction = 'load-more';
            loadMore.dataset.nextPage = nextPage;
            loadMore.textContent = 'Load more reservations';
            reservationList.append(loadMore);
        }
    }

    async function loadReservations(url = reservationList.dataset.endpoint, append = false) {
        const requestId = ++activeListRequest;
        reservationListMessage.textContent = 'Loading reservations…';
        reservationListMessage.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Could not load reservations.');
            if (requestId !== activeListRequest) return;
            renderReservations(payload.data || [], payload.next_page_url, append);
            reservationListMessage.textContent = `${payload.total ?? (payload.data || []).length} reservation(s) found.`;
        } catch (error) {
            if (requestId !== activeListRequest) return;
            reservationListMessage.textContent = error.message;
            if (!append) reservationList.replaceChildren();
        } finally {
            if (requestId === activeListRequest) reservationListMessage.removeAttribute('aria-busy');
        }
    }

    reservationList.addEventListener('reservations:reload', loadReservations);
    reservationList.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-reservation-action]');
        if (!button || button.disabled) return;

        if (button.dataset.reservationAction === 'load-more') {
            button.disabled = true;
            await loadReservations(button.dataset.nextPage, true);
            return;
        }

        const reservationId = button.dataset.reservationId;
        const action = button.dataset.reservationAction;
        const baseEndpoint = `${reservationList.dataset.editEndpoint}/${reservationId}`;

        if (action === 'edit') {
            const response = await fetch(baseEndpoint, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) {
                reservationListMessage.textContent = payload.message || 'Could not open this reservation.';
                return;
            }
            if (!editForm) return;
            const reservation = payload.data;
            editForm.elements.room_id.value = reservation.room_id;
            editForm.elements.purpose.value = reservation.purpose;
            editForm.elements.organization_name.value = reservation.organization_name || '';
            editForm.elements.starts_at.value = new Date(new Date(reservation.starts_at).getTime() - new Date(reservation.starts_at).getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            editForm.elements.ends_at.value = new Date(new Date(reservation.ends_at).getTime() - new Date(reservation.ends_at).getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            editForm.elements.notes.value = reservation.notes || '';
            editForm.dataset.reservationId = reservation.id;
            editForm.dataset.method = 'PUT';
            const formHeading = editForm.closest('.reservation-guide')?.querySelector('#reservation-guide-title');
            const formSubmit = editForm.querySelector('button[type="submit"]');
            if (formHeading) formHeading.textContent = `Edit reservation ${reservation.room?.code || ''}`;
            if (formSubmit) formSubmit.textContent = 'Save reservation changes';
            if (formSubmit && !editForm.querySelector('[data-reservation-cancel-edit]')) {
                const cancelEdit = document.createElement('button');
                cancelEdit.type = 'button';
                cancelEdit.className = 'text-link';
                cancelEdit.dataset.reservationCancelEdit = '';
                cancelEdit.textContent = 'Stop editing';
                cancelEdit.addEventListener('click', () => {
                    editForm.reset();
                    editForm.dataset.reservationId = '';
                    editForm.dataset.method = 'POST';
                    if (formHeading) formHeading.textContent = 'From schedule to confirmation';
                    if (formSubmit) formSubmit.textContent = 'Submit reservation request';
                    cancelEdit.remove();
                });
                formSubmit.before(cancelEdit);
            }
            editForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        let endpoint = `${baseEndpoint}/cancel`;
        let body = {};
        if (action === 'approve' || action === 'reject') {
            endpoint = `${baseEndpoint}/review`;
            body.status = action === 'approve' ? 'approved' : 'rejected';
            if (action === 'reject') {
                body.decision_reason = window.prompt('Enter reason for rejecting this request:')?.trim();
                if (!body.decision_reason) return;
            }
        } else if (action === 'cancel') {
            if (!window.confirm('Cancel this reservation?')) return;
            body.cancellation_reason = window.prompt('Cancellation reason (optional):')?.trim() || '';
        }

        button.disabled = true;
        showCveLoader(action === 'approve' ? 'Approving reservation…' : action === 'reject' ? 'Rejecting reservation…' : 'Updating reservation…');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Reservation action failed.');
            reservationListMessage.textContent = 'Reservation updated.';
            await loadReservations();
        } catch (error) {
            reservationListMessage.textContent = error.message;
            button.disabled = false;
        } finally {
            hideCveLoader();
        }
    });

    loadReservations();
}

const inventoryForm = document.querySelector('[data-inventory-form]');
const inventoryList = document.querySelector('[data-inventory-list]');

if (inventoryForm && inventoryList) {
    const inventoryFormMessage = inventoryForm.querySelector('[data-inventory-form-message]');
    const inventoryListMessage = document.querySelector('[data-inventory-list-message]');
    const inventorySubmit = inventoryForm.querySelector('button[type="submit"]');

    function resetInventoryForm() {
        inventoryForm.reset();
        inventoryForm.dataset.equipmentId = '';
        inventoryForm.dataset.method = 'POST';
        inventorySubmit.textContent = 'Add equipment';
        inventoryForm.querySelector('[data-inventory-cancel-edit]')?.remove();
    }

    function renderInventory(items) {
        inventoryList.replaceChildren();
        items.forEach((item) => {
            const row = document.createElement('article');
            row.className = 'request-row inventory-admin-row';
            const details = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = `${item.name} · ${item.category || 'Other equipment'}`;
            const count = document.createElement('span');
            count.textContent = `${item.available_quantity} of ${item.quantity} available · ${item.status}`;
            details.append(name, count);
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'text-link';
            edit.dataset.inventoryEdit = item.id;
            edit.textContent = 'Edit';
            row.append(details, edit);
            inventoryList.append(row);
        });
        if (items.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'directory-message';
            empty.textContent = 'No equipment records are stored yet.';
            inventoryList.append(empty);
        }
    }

    async function loadInventory() {
        inventoryListMessage.textContent = 'Loading inventory…';
        inventoryListMessage.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(inventoryList.dataset.endpoint, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Could not load inventory.');
            renderInventory(payload.data || []);
            inventoryListMessage.textContent = `${(payload.data || []).length} inventory records loaded.`;
        } catch (error) {
            inventoryList.replaceChildren();
            inventoryListMessage.textContent = error.message;
        } finally {
            inventoryListMessage.removeAttribute('aria-busy');
        }
    }

    inventoryForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        inventorySubmit.disabled = true;
        showCveLoader('Saving equipment inventory…');
        try {
            const equipmentId = inventoryForm.dataset.equipmentId;
            const response = await fetch(equipmentId ? `${inventoryForm.dataset.storeEndpoint}/${equipmentId}` : inventoryForm.dataset.endpoint, {
                method: inventoryForm.dataset.method || 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': inventoryForm.querySelector('input[name="_token"]').value,
                },
                body: JSON.stringify(Object.fromEntries(new FormData(inventoryForm))),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Inventory could not be saved.');
            resetInventoryForm();
            inventoryFormMessage.textContent = equipmentId ? 'Equipment changes saved.' : 'Equipment added.';
            inventoryFormMessage.className = 'form-message is-success';
            await loadInventory();
        } catch (error) {
            inventoryFormMessage.textContent = error.message;
            inventoryFormMessage.className = 'form-message is-error';
        } finally {
            inventorySubmit.disabled = false;
            hideCveLoader();
        }
    });

    inventoryList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-inventory-edit]');
        if (!button) return;
        const equipmentId = button.dataset.inventoryEdit;

        fetch(`${inventoryList.dataset.itemEndpoint}/${equipmentId}`, { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Could not open inventory item.');
                const equipment = payload.data;
                inventoryForm.elements.name.value = equipment.name;
                inventoryForm.elements.category.value = equipment.category || '';
                inventoryForm.elements.quantity.value = equipment.quantity;
                inventoryForm.elements.status.value = equipment.status;
                inventoryForm.elements.description.value = equipment.description || '';
                inventoryForm.dataset.equipmentId = equipment.id;
                inventoryForm.dataset.method = 'PUT';
                inventorySubmit.textContent = 'Save equipment';
                if (!inventoryForm.querySelector('[data-inventory-cancel-edit]')) {
                    const cancelEdit = document.createElement('button');
                    cancelEdit.type = 'button';
                    cancelEdit.className = 'text-link';
                    cancelEdit.dataset.inventoryCancelEdit = '';
                    cancelEdit.textContent = 'Stop editing';
                    cancelEdit.addEventListener('click', resetInventoryForm);
                    inventorySubmit.before(cancelEdit);
                }
                inventoryForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch((error) => {
                inventoryListMessage.textContent = error.message;
            });
    });

    loadInventory();
}

const equipmentRequestForm = document.querySelector('[data-equipment-request-form]');
const equipmentRequestList = document.querySelector('[data-equipment-request-list]');

if (equipmentRequestList) {
    const requestListMessage = document.querySelector('[data-equipment-request-list-message]');
    const isEquipmentReviewer = equipmentRequestList.dataset.reviewer === 'true';
    let activeEquipmentRequestListId = 0;

    function equipmentRequestAction(label, action, requestId) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'text-link';
        button.dataset.equipmentRequestAction = action;
        button.dataset.equipmentRequestId = requestId;
        button.textContent = label;
        return button;
    }

    function renderEquipmentRequests(requests, nextPage, append = false) {
        if (!append) equipmentRequestList.replaceChildren();

        requests.forEach((request) => {
            const row = document.createElement('article');
            row.className = 'request-row equipment-request-row';
            const details = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = `${request.equipment?.name || 'Equipment'} · ${request.quantity} unit${request.quantity === 1 ? '' : 's'}`;
            const schedule = document.createElement('span');
            const bookingDate = request.reservation?.starts_at ? new Date(request.reservation.starts_at).toLocaleString() : 'Reservation date unavailable';
            const roomCode = request.reservation?.room?.code || 'Room';
            schedule.textContent = `${roomCode} · ${bookingDate}`;
            const status = document.createElement('span');
            status.className = `reservation-state ${request.status === 'pending' ? 'is-pending' : ''}`;
            status.textContent = request.status[0].toUpperCase() + request.status.slice(1);
            details.append(title, schedule, status);

            const actions = document.createElement('div');
            actions.className = 'reservation-actions';
            const isUpcoming = request.reservation?.starts_at && new Date(request.reservation.starts_at) > new Date();
            if (!isEquipmentReviewer && request.status === 'pending' && isUpcoming) {
                actions.append(equipmentRequestAction('Edit', 'edit', request.id));
            }
            if (isEquipmentReviewer && request.status === 'pending' && isUpcoming) {
                actions.append(
                    equipmentRequestAction('Approve', 'approve', request.id),
                    equipmentRequestAction('Reject', 'reject', request.id),
                );
            }
            if (isEquipmentReviewer && request.status === 'approved' && isUpcoming) {
                actions.append(equipmentRequestAction('Release', 'release', request.id));
            }
            if (isEquipmentReviewer && request.status === 'released') {
                actions.append(equipmentRequestAction('Mark returned', 'return', request.id));
            }
            if (['pending', 'approved'].includes(request.status) && isUpcoming) {
                actions.append(equipmentRequestAction('Cancel', 'cancel', request.id));
            }
            if (isEquipmentReviewer && request.requester) {
                const requester = document.createElement('small');
                requester.className = 'reservation-requester';
                requester.textContent = `Requested by ${request.requester.name}`;
                details.append(requester);
            }

            row.append(details, actions);
            equipmentRequestList.append(row);
        });

        if (requests.length === 0 && !append) {
            const empty = document.createElement('p');
            empty.className = 'directory-message';
            empty.textContent = 'No equipment requests found.';
            equipmentRequestList.append(empty);
        }

        equipmentRequestList.querySelector('[data-equipment-request-action="load-more"]')?.remove();
        if (nextPage) {
            const loadMore = document.createElement('button');
            loadMore.type = 'button';
            loadMore.className = 'text-link';
            loadMore.dataset.equipmentRequestAction = 'load-more';
            loadMore.dataset.nextPage = nextPage;
            loadMore.textContent = 'Load more requests';
            equipmentRequestList.append(loadMore);
        }
    }

    async function loadEquipmentRequests(url = equipmentRequestList.dataset.endpoint, append = false) {
        const requestId = ++activeEquipmentRequestListId;
        requestListMessage.textContent = 'Loading equipment requests…';
        requestListMessage.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Could not load equipment requests.');
            if (requestId !== activeEquipmentRequestListId) return;
            renderEquipmentRequests(payload.data || [], payload.next_page_url, append);
            requestListMessage.textContent = `${payload.total ?? (payload.data || []).length} request(s) found.`;
        } catch (error) {
            if (requestId !== activeEquipmentRequestListId) return;
            requestListMessage.textContent = error.message;
            if (!append) equipmentRequestList.replaceChildren();
        } finally {
            if (requestId === activeEquipmentRequestListId) requestListMessage.removeAttribute('aria-busy');
        }
    }

    if (equipmentRequestForm) {
        const formMessage = equipmentRequestForm.querySelector('[data-equipment-request-message]');
        const submitButton = equipmentRequestForm.querySelector('button[type="submit"]');

        function resetEquipmentRequestForm() {
            equipmentRequestForm.reset();
            equipmentRequestForm.dataset.requestId = '';
            equipmentRequestForm.dataset.method = 'POST';
            submitButton.textContent = 'Submit equipment request';
            equipmentRequestForm.querySelector('[data-request-cancel-edit]')?.remove();
        }

        equipmentRequestForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            submitButton.disabled = true;
            showCveLoader('Saving equipment request…');
            try {
                const requestId = equipmentRequestForm.dataset.requestId;
                const response = await fetch(requestId ? `${equipmentRequestForm.dataset.itemEndpoint}/${requestId}` : equipmentRequestForm.dataset.endpoint, {
                    method: equipmentRequestForm.dataset.method || 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': equipmentRequestForm.querySelector('input[name="_token"]').value,
                    },
                    body: JSON.stringify(Object.fromEntries(new FormData(equipmentRequestForm))),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Equipment request could not be saved.');
                resetEquipmentRequestForm();
                formMessage.textContent = requestId ? 'Equipment request updated.' : 'Equipment request submitted.';
                formMessage.className = 'form-message is-success';
                await loadEquipmentRequests();
            } catch (error) {
                formMessage.textContent = error.message;
                formMessage.className = 'form-message is-error';
            } finally {
                submitButton.disabled = false;
                hideCveLoader();
            }
        });
    }

    equipmentRequestList.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-equipment-request-action]');
        if (!button || button.disabled) return;
        if (button.dataset.equipmentRequestAction === 'load-more') {
            button.disabled = true;
            await loadEquipmentRequests(button.dataset.nextPage, true);
            return;
        }

        const action = button.dataset.equipmentRequestAction;
        const requestId = button.dataset.equipmentRequestId;
        const endpoint = `${equipmentRequestList.dataset.itemEndpoint}/${requestId}`;
        if (action === 'edit') {
            if (!equipmentRequestForm) return;
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) {
                requestListMessage.textContent = payload.message || 'Could not open equipment request.';
                return;
            }
            const request = payload.data;
            equipmentRequestForm.elements.equipment_id.value = request.equipment_id;
            equipmentRequestForm.elements.reservation_id.value = request.reservation_id;
            equipmentRequestForm.elements.quantity.value = request.quantity;
            equipmentRequestForm.elements.notes.value = request.notes || '';
            equipmentRequestForm.dataset.requestId = request.id;
            equipmentRequestForm.dataset.method = 'PUT';
            const submitButton = equipmentRequestForm.querySelector('button[type="submit"]');
            submitButton.textContent = 'Save request changes';
            if (!equipmentRequestForm.querySelector('[data-request-cancel-edit]')) {
                const cancelEdit = document.createElement('button');
                cancelEdit.type = 'button';
                cancelEdit.className = 'text-link';
                cancelEdit.dataset.requestCancelEdit = '';
                cancelEdit.textContent = 'Stop editing';
                cancelEdit.addEventListener('click', () => {
                    equipmentRequestForm.reset();
                    equipmentRequestForm.dataset.requestId = '';
                    equipmentRequestForm.dataset.method = 'POST';
                    submitButton.textContent = 'Submit equipment request';
                    cancelEdit.remove();
                });
                submitButton.before(cancelEdit);
            }
            equipmentRequestForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        let target = `${endpoint}/cancel`;
        const body = {};
        if (action === 'approve' || action === 'reject') {
            target = `${endpoint}/review`;
            body.status = action === 'approve' ? 'approved' : 'rejected';
            if (action === 'reject') {
                body.decision_reason = window.prompt('Enter a reason for rejecting this request:')?.trim();
                if (!body.decision_reason) return;
            }
        } else if (action === 'release') {
            target = `${endpoint}/release`;
            if (!window.confirm('Release the approved equipment to the requester?')) return;
        } else if (action === 'return') {
            target = `${endpoint}/return`;
            body.return_notes = window.prompt('Return notes (optional):')?.trim() || '';
        } else if (action === 'cancel') {
            if (!window.confirm('Cancel this equipment request?')) return;
            body.cancellation_reason = window.prompt('Cancellation reason (optional):')?.trim() || '';
        }

        button.disabled = true;
        showCveLoader(action === 'approve' ? 'Approving equipment…' : action === 'release' ? 'Releasing equipment…' : action === 'return' ? 'Recording equipment return…' : 'Updating equipment request…');
        try {
            const response = await fetch(target, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Equipment request action failed.');
            await loadEquipmentRequests();
        } catch (error) {
            requestListMessage.textContent = error.message;
            button.disabled = false;
        } finally {
            hideCveLoader();
        }
    });

    loadEquipmentRequests();
}

const sidebarToggle = document.querySelector('#sidebar-toggle');
const shell = document.querySelector('#app-shell');
const mobileScrim = document.querySelector('#mobile-scrim');

if (sidebarToggle && shell && mobileScrim) {
    function setMobileNavigation(open) {
        shell.classList.toggle('mobile-nav-open', open);
        mobileScrim.hidden = !open;
        sidebarToggle.setAttribute('aria-expanded', String(open));
        sidebarToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        sidebarToggle.title = open ? 'Close navigation' : 'Open navigation';
    }

    sidebarToggle.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 680px)').matches) {
            setMobileNavigation(!shell.classList.contains('mobile-nav-open'));
        } else {
            const collapsed = shell.classList.toggle('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
            sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
        }
    });

    mobileScrim.addEventListener('click', () => setMobileNavigation(false));
    document.querySelectorAll('.sidebar a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 680px)').matches) {
                setMobileNavigation(false);
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMobileNavigation(false);
        }
    });

    if (window.matchMedia('(max-width: 680px)').matches) {
        setMobileNavigation(false);
    }
}

const popoverItems = [
    { trigger: document.querySelector('#notification-toggle'), panel: document.querySelector('#notification-popover') },
    { trigger: document.querySelector('#profile-menu-toggle'), panel: document.querySelector('#profile-menu') },
].filter(({ trigger, panel }) => trigger && panel);

function closePopovers(except = null) {
    popoverItems.forEach(({ trigger, panel }) => {
        if (panel === except) {
            return;
        }

        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    });
}

popoverItems.forEach(({ trigger, panel }) => {
    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = panel.hidden;
        closePopovers(willOpen ? panel : null);
        panel.hidden = !willOpen;
        trigger.setAttribute('aria-expanded', String(willOpen));
        if (willOpen && trigger.id === 'notification-toggle') {
            trigger.querySelector('.notification-dot')?.setAttribute('hidden', 'hidden');
            panel.querySelectorAll('[data-notification-id].is-unread').forEach((item) => {
                item.classList.remove('is-unread');
                fetch(`/notifications/${item.dataset.notificationId}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '', Accept: 'application/json' },
                }).catch(() => {});
            });
        }
    });
});

document.addEventListener('click', (event) => {
    if (!popoverItems.some(({ trigger, panel }) => trigger.contains(event.target) || panel.contains(event.target))) {
        closePopovers();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closePopovers();
    }
});

const toast = document.querySelector('#app-toast');
const toastDetail = toast?.querySelector('[data-toast-detail]');
const toastMessage = toast?.querySelector('[data-toast-message]');
const toastToggle = toast?.querySelector('[data-toast-toggle]');
const toastClose = toast?.querySelector('[data-toast-close]');
const toastPause = toast?.querySelector('[data-toast-pause]');
const toastProgress = toast?.querySelector('[data-toast-progress]');
let toastTimer;
let toastTick;
let toastRemaining = 9;
let toastPaused = false;

function hideToast() {
    if (!toast) return;
    toast.hidden = true;
    clearTimeout(toastTimer);
    clearInterval(toastTick);
}

function updateToastCountdown() {
    if (!toastMessage || !toastPause) return;
    if (toastPaused) {
        toastPause.textContent = 'Click to resume.';
        return;
    }
    toastPause.textContent = `This message will close in ${toastRemaining} seconds. Click to keep open.`;
    if (toastProgress) toastProgress.style.transform = `scaleX(${Math.max(toastRemaining, 0) / 9})`;
}

function showToast(message) {
    if (!toast || !message) return;
    toastMessage.textContent = message;
    toast.hidden = false;
    toastRemaining = 9;
    toastPaused = false;
    toastDetail?.setAttribute('hidden', '');
    toastToggle?.setAttribute('aria-expanded', 'false');
    toastToggle?.setAttribute('aria-label', 'Expand notification');
    updateToastCountdown();
    clearTimeout(toastTimer);
    clearInterval(toastTick);
    toastTick = setInterval(() => {
        if (toastPaused) return;
        toastRemaining -= 1;
        updateToastCountdown();
    }, 1000);
    toastTimer = setTimeout(hideToast, 9000);
}

if (toast?.dataset.serverToast) showToast(toast.dataset.serverToast);

toastToggle?.addEventListener('click', () => {
    const willExpand = toastDetail?.hasAttribute('hidden');
    if (willExpand) toastDetail?.removeAttribute('hidden');
    else toastDetail?.setAttribute('hidden', '');
    toastToggle.setAttribute('aria-expanded', String(willExpand));
    toastToggle.setAttribute('aria-label', willExpand ? 'Collapse notification' : 'Expand notification');
});
toastClose?.addEventListener('click', hideToast);
toastPause?.addEventListener('click', () => {
    toastPaused = !toastPaused;
    if (toastPaused) clearTimeout(toastTimer);
    else toastTimer = setTimeout(hideToast, toastRemaining * 1000);
    updateToastCountdown();
});

document.querySelectorAll('[data-toast]').forEach((button) => {
    button.addEventListener('click', () => {
        showToast(button.dataset.toast);
        closePopovers();
    });
});

function formatNotificationTime(createdAt) {
    const elapsed = Math.max(0, Math.floor((Date.now() - Date.parse(createdAt)) / 1000));
    if (elapsed < 60) return `${elapsed} second${elapsed === 1 ? '' : 's'} ago`;
    const minutes = Math.floor(elapsed / 60);
    if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? '' : 's'} ago`;
}

function refreshNotificationTimes() {
    document.querySelectorAll('[data-created-at]').forEach((element) => {
        element.textContent = formatNotificationTime(element.dataset.createdAt);
    });
}

refreshNotificationTimes();
window.setInterval(refreshNotificationTimes, 1000);

const availabilityFilters = document.querySelector('#availability-filters');
const availabilityResults = document.querySelector('#availability-results');
const scheduleFilters = document.querySelector('#schedule-filters');
const scheduleList = document.querySelector('#schedule-list');
const pageStatusLabels = { available: 'Available', occupied: 'Occupied', reserved: 'Reserved', maintenance: 'Maintenance', unavailable: 'Unavailable' };

function formatFloor(floor) {
    const number = Number(floor) + 1;
    const suffix = number === 2 ? 'nd' : (number === 3 ? 'rd' : 'th');
    return `${number}${suffix} floor`;
}

function renderAvailabilityResults(rooms) {
    if (!availabilityResults) return;
    availabilityResults.replaceChildren();
    if (!rooms.length) {
        const empty = document.createElement('p');
        empty.className = 'availability-note';
        empty.textContent = 'No rooms match this availability window.';
        availabilityResults.append(empty);
        return;
    }
    rooms.forEach((room) => {
        const card = document.createElement('article');
        card.className = 'room-directory-card';
        const top = document.createElement('div');
        top.className = 'room-directory-card-top';
        const heading = document.createElement('div');
        const name = document.createElement('h3');
        name.textContent = room.name;
        const number = document.createElement('p');
        number.className = 'room-directory-number';
        number.textContent = room.id;
        heading.append(name, number);
        const badge = document.createElement('span');
        badge.className = `status-badge ${room.status}`;
        badge.textContent = pageStatusLabels[room.status] || 'Unknown status';
        top.append(heading, badge);
        const facts = document.createElement('p');
        facts.className = 'directory-facts';
        facts.textContent = `${formatFloor(room.floor)} · ${room.capacity} seats · Next: ${room.next}`;
        const action = document.createElement('a');
        action.className = 'secondary-button';
        action.href = `/floor-plan?floor=${room.floor}`;
        action.textContent = 'View on floor plan';
        card.append(top, facts, action);
        availabilityResults.append(card);
    });
}

function renderScheduleItems(items, target) {
    if (!target) return;
    target.replaceChildren();
    if (!items.length) {
        const empty = document.createElement('li');
        empty.className = 'upcoming-empty';
        empty.textContent = 'No reservations scheduled for this floor on this date.';
        target.append(empty);
        return;
    }
    items.forEach((item, index) => {
        const starts = new Date(item.starts_at);
        const ends = new Date(item.ends_at);
        const row = document.createElement('li');
        const time = document.createElement('span');
        time.className = 'schedule-time';
        time.textContent = starts.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const track = document.createElement('span');
        track.className = 'schedule-track';
        const dot = document.createElement('i');
        dot.className = `track-dot ${index % 2 ? 'track-amber' : 'track-blue'}`;
        track.append(dot);
        const details = document.createElement('span');
        details.className = 'schedule-details';
        const title = document.createElement('strong');
        title.textContent = item.title;
        const meta = document.createElement('small');
        meta.textContent = `${item.room} · ${starts.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}–${ends.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        details.append(title, meta);
        const state = document.createElement('span');
        state.className = `reservation-state ${item.status === 'pending' ? 'is-pending' : ''}`;
        state.textContent = item.status === 'pending' ? 'Pending' : 'Approved';
        row.append(time, track, details, state);
        target.append(row);
    });
}

async function loadFloorPlanData(filters, onSuccess, messageElement) {
    const endpoint = filters?.dataset.endpoint;
    if (!endpoint) return;
    const url = new URL(endpoint, window.location.origin);
    filters.querySelectorAll('input, select').forEach((input) => {
        if (input.value) url.searchParams.set(input.id.replace(/^availability-|^schedule-/, '').replace('-input', ''), input.value);
    });
    if (messageElement) messageElement.textContent = 'Loading live room data…';
    showCveLoader('Refreshing live room data…');
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Request failed');
        const payload = await response.json();
        onSuccess(payload);
        if (messageElement) messageElement.textContent = `Updated ${new Date(payload.meta.generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}.`;
    } catch {
        if (messageElement) messageElement.textContent = 'Live room data could not be loaded. Try again shortly.';
    } finally {
        hideCveLoader();
    }
}

if (availabilityFilters && availabilityResults) {
    const message = document.querySelector('#availability-message');
    const refresh = () => loadFloorPlanData(availabilityFilters, (payload) => renderAvailabilityResults(payload.data || []), message);
    availabilityFilters.querySelectorAll('input, select').forEach((input) => input.addEventListener('change', refresh));
    refresh();
}

if (scheduleFilters && scheduleList) {
    const message = document.querySelector('#schedule-message');
    const refresh = () => loadFloorPlanData(scheduleFilters, (payload) => renderScheduleItems(payload.upcoming || [], scheduleList), message);
    scheduleFilters.querySelectorAll('input, select').forEach((input) => input.addEventListener('change', refresh));
    refresh();
}

const directoryMessage = document.querySelector('#directory-message');
const directoryGrid = document.querySelector('#room-directory-grid');

if (directoryMessage && directoryGrid) {
    const buildingSelect = document.querySelector('#directory-building');
    const floorSelect = document.querySelector('#directory-floor');
    const statusSelect = document.querySelector('#directory-status');
    const typeSelect = document.querySelector('#directory-type');
    const capacityInput = document.querySelector('#directory-capacity');
    const statuses = {
        available: 'Available',
        occupied: 'Occupied',
        reserved: 'Reserved',
        maintenance: 'Maintenance',
        unavailable: 'Unavailable',
    };
    let requestId = 0;
    let capacityTimer;

    function renderDirectory(rooms) {
        directoryGrid.replaceChildren();

        rooms.forEach((room) => {
            const card = document.createElement('article');
            card.className = 'room-directory-card';

            const top = document.createElement('div');
            top.className = 'room-directory-card-top';
            const heading = document.createElement('div');
            const label = document.createElement('p');
            label.className = 'eyebrow';
            label.textContent = 'DATABASE SPACE';
            const name = document.createElement('h3');
            name.textContent = room.name;
            const number = document.createElement('p');
            number.className = 'room-directory-number';
            number.textContent = `Room ${room.id}`;
            heading.append(label, name, number);

            const badge = document.createElement('span');
            const statusLabel = statuses[room.status] ?? 'Unknown status';
            badge.className = `status-badge ${statuses[room.status] ? room.status : 'unavailable'}`;
            badge.textContent = statusLabel;
            top.append(heading, badge);

            const facts = document.createElement('dl');
            facts.className = 'directory-facts';
            [
                ['Building', room.building],
                ['Floor', room.floor],
                ['Capacity', `${room.capacity} seats`],
                ['Room type', room.type || 'General room'],
                ['Equipment', room.equipment],
                ['Next availability', room.next],
            ].forEach(([term, value]) => {
                const item = document.createElement('div');
                const description = document.createElement('dt');
                description.textContent = term;
                const detail = document.createElement('dd');
                detail.textContent = value;
                item.append(description, detail);
                facts.append(item);
            });

            const actions = document.createElement('div');
            actions.className = 'room-card-actions';
            const floorPlanLink = document.createElement('a');
            floorPlanLink.className = 'text-link';
            floorPlanLink.textContent = 'View in 3D';
            const floorPlanUrl = new URL(directoryGrid.dataset.floorPlanUrl, window.location.origin);
            floorPlanUrl.searchParams.set('floor', room.floor_number);
            floorPlanLink.href = floorPlanUrl.toString();

            const reservationLink = document.createElement('a');
            reservationLink.className = 'text-link';
            reservationLink.href = directoryGrid.dataset.reservationsUrl;
            reservationLink.textContent = 'Reservation steps';

            const scheduleLink = document.createElement('a');
            scheduleLink.className = 'text-link';
            scheduleLink.href = directoryGrid.dataset.scheduleUrl;
            scheduleLink.textContent = 'View schedule';
            actions.append(floorPlanLink, reservationLink, scheduleLink);

            card.append(top, facts, actions);
            directoryGrid.append(card);
        });

        directoryMessage.textContent = rooms.length
            ? `${rooms.length} room${rooms.length === 1 ? '' : 's'} match the selected filters.`
            : 'No rooms match the selected filters.';
    }

    async function loadDirectory() {
        const activeRequestId = ++requestId;
        directoryMessage.textContent = 'Loading room inventory…';
        directoryGrid.setAttribute('aria-busy', 'true');
        showCveLoader('Loading room inventory…');

        try {
            const url = new URL(directoryMessage.dataset.endpoint, window.location.origin);
            const filters = {
                building_id: buildingSelect?.value,
                floor_id: floorSelect?.value,
                type: typeSelect?.value,
                capacity: capacityInput?.value,
                status: statusSelect?.value,
            };

            Object.entries(filters).forEach(([key, value]) => {
                if (value && value !== 'all') {
                    url.searchParams.set(key, value);
                }
            });

            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error(`Room directory request failed with status ${response.status}.`);
            }

            const payload = await response.json();
            if (!Array.isArray(payload.data) || activeRequestId !== requestId) {
                return;
            }

            renderDirectory(payload.data);
        } catch (error) {
            if (activeRequestId !== requestId) {
                return;
            }

            directoryGrid.replaceChildren();
            directoryMessage.textContent = 'Room inventory could not be loaded. Check the connection and try again.';
            console.error(error);
        } finally {
            if (activeRequestId === requestId) {
                directoryGrid.setAttribute('aria-busy', 'false');
                hideCveLoader();
            }
        }
    }

    buildingSelect?.addEventListener('change', () => {
        if (floorSelect) {
            floorSelect.value = '';
        }

        loadDirectory();
    });
    [floorSelect, statusSelect, typeSelect].forEach((select) => select?.addEventListener('change', loadDirectory));
    capacityInput?.addEventListener('input', () => {
        window.clearTimeout(capacityTimer);
        capacityTimer = window.setTimeout(loadDirectory, 250);
    });
    loadDirectory();
}
