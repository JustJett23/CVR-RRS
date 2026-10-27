const viewer = document.querySelector('#floor-plan');
const mapStage = document.querySelector('#map-stage');
const cveLoader = document.querySelector('[data-cve-loader]');
const cveLoaderMessage = cveLoader?.querySelector('[data-cve-loader-message]');
const landingOrbit = document.querySelector('.landing-orbit');

async function readJsonResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.toLowerCase().includes('application/json')) {
        await response.text();
        if (response.redirected || response.status === 401 || response.status === 419) {
            throw new Error('Your session may have expired. Sign in again, then retry.');
        }
        throw new Error('The server returned a page instead of data. Refresh and try again.');
    }

    try {
        return await response.json();
    } catch {
        throw new Error('The server returned an unreadable response. Please retry.');
    }
}

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
if (chatbotForm && chatbotMessages) {
    let requestPending = false;

    function appendChatMessage(message, isUser = false) {
        const element = document.createElement('p');
        element.className = `chatbot-message${isUser ? ' is-user' : ''}`;
        element.textContent = message;
        chatbotMessages.append(element);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    async function submitQuestion(question) {
        if (requestPending) return;
        const safeQuestion = question.trim().slice(0, 240);
        if (!safeQuestion) return;
        requestPending = true;
        appendChatMessage(safeQuestion, true);

        const submitButton = chatbotForm.querySelector('[type="submit"]');
        const suggestions = [...document.querySelectorAll('[data-chatbot-suggestion]')];
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.querySelector('span').textContent = 'Thinking…';
        }
        suggestions.forEach((button) => { button.disabled = true; });

        try {
            const response = await fetch(chatbotForm.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': chatbotForm.dataset.csrf,
                },
                body: JSON.stringify({ question: safeQuestion }),
            });
            const payload = await readJsonResponse(response);
            if (!response.ok) {
                throw new Error(payload.errors?.question?.[0] || payload.message || 'The assistant could not answer right now. Please try again.');
            }
            appendChatMessage(payload.answer);
        } catch (error) {
            appendChatMessage(error.message || 'The assistant is temporarily unavailable. Please try again shortly.');
        } finally {
            requestPending = false;
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.querySelector('span').textContent = 'Send';
            }
            suggestions.forEach((button) => { button.disabled = false; });
        }
    }

    chatbotForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const input = chatbotForm.elements.question;
        submitQuestion(input.value);
        input.value = '';
        input.focus();
    });

    document.querySelectorAll('[data-chatbot-suggestion]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = chatbotForm.elements.question;
            input.value = button.dataset.chatbotSuggestion || '';
            chatbotForm.requestSubmit();
        });
    });
}

const faqSearch = document.querySelector('[data-faq-search]');
if (faqSearch) {
    const faqItems = [...document.querySelectorAll('[data-faq-item]')];
    const categories = [...document.querySelectorAll('[data-faq-category]')];
    const emptyMessage = document.querySelector('[data-faq-empty]');
    faqSearch.addEventListener('input', () => {
        const query = faqSearch.value.trim().toLocaleLowerCase();
        let visibleCount = 0;
        faqItems.forEach((item) => {
            const matches = item.textContent.toLocaleLowerCase().includes(query);
            item.hidden = !matches;
            if (matches) visibleCount += 1;
        });
        categories.forEach((category) => {
            category.hidden = !category.querySelector('[data-faq-item]:not([hidden])');
        });
        if (emptyMessage) emptyMessage.hidden = visibleCount > 0;
    });
}

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
    const floorNames = ['1F', '2F', '3F', '4F', 'RD'];
    const floorLabels = ['FLOOR 1', 'FLOOR 2', 'FLOOR 3', 'FLOOR 4', 'ROOF'];
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
            facts.textContent = `${room.floorName} · ${room.capacity > 0 ? `${room.capacity} seats` : 'Capacity not listed'}`;
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
            const payload = await readJsonResponse(response);
            if (!response.ok) throw new Error(payload.message || `Floor data request failed with status ${response.status}.`);
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
        if (floorLabel) floorLabel.textContent = floorLabels[index] || 'FLOOR';
        floorBack?.removeAttribute('hidden');
    }

    function selectedModelFloor(value) {
        if (value === 'floor-1f') return 0;
        if (value === 'roof') return 4;
        const floorNumber = Number(value);
        return Number.isInteger(floorNumber) && floorNumber >= 1 && floorNumber <= 4 ? floorNumber : null;
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
    async function loadFloorPlanAsset() {
        const modelUrl = new URL(viewer.dataset.campusModel, document.baseURI);
        const cacheName = 'cve-floor-plan-model-v1';
        let modelCache;

        try {
            modelCache = await window.caches?.open(cacheName);
            const cachedResponse = await modelCache?.match(modelUrl.href);
            if (cachedResponse) {
                try {
                    return await loader.parseAsync(await cachedResponse.arrayBuffer(), new URL('.', modelUrl).href);
                } catch {
                    await modelCache.delete(modelUrl.href);
                }
            }
        } catch {
            modelCache = null;
        }

        const response = await fetch(modelUrl.href, { cache: 'force-cache' });
        if (!response.ok) throw new Error(`3D model request failed (${response.status}).`);
        const modelData = await response.arrayBuffer();
        try {
            modelCache ??= await window.caches?.open(cacheName);
            await modelCache?.put(modelUrl.href, new Response(modelData.slice(0), {
                headers: { 'Content-Type': response.headers.get('content-type') || 'model/gltf-binary' },
            }));
        } catch {
            // Continue even when persistent browser caching is unavailable.
        }

        return loader.parseAsync(modelData, new URL('.', modelUrl).href);
    }

    loadFloorPlanAsset().then((asset) => {
        modelRoot.clear();
        const source = asset.scene;
        source.updateMatrixWorld(true);
        floorGroups = floorNames.map((name) => {
            const group = new THREE.Group();
            group.name = `Floor ${name}`;
            group.userData.floorName = name;
            return group;
        });
        const floorPattern = /^(?:NPC[_ -])?(1F|2F|3F|4F|RD|RF|ROOF)(?=[_ -]|$)/i;
        [...source.children].forEach((node) => {
            const match = node.name.match(floorPattern);
            const matchedFloor = match?.[1].toUpperCase();
            let floorIndex = matchedFloor ? floorNames.indexOf(['RF', 'ROOF'].includes(matchedFloor) ? 'RD' : matchedFloor) : -1;
            if (floorIndex < 0) {
                floorIndex = /basketball key marking/i.test(node.name) ? 3 : 1;
            }
            floorGroups[floorIndex].add(node);
        });
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
        const selectedIndex = selectedModelFloor(selectedValue);
        if (selectedValue !== 'all' && selectedIndex !== null && floorGroups[selectedIndex]) {
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
        const selectedDataFloor = floorSelect?.value || 'all';
        if (['all', '1', '2', '3', '4'].includes(selectedDataFloor)) {
            loadFloorData(selectedDataFloor);
        }
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
        const selected = selectedModelFloor(floorSelect.value);
        if (floorSelect.value === 'all') showAllFloors();
        else if (selected !== null && floorGroups[selected]) focusFloor(floorGroups[selected], selected);
        if (['all', '1', '2', '3', '4'].includes(floorSelect.value)) {
            loadFloorData(floorSelect.value);
        }
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

if (reservationForm) {
    const startsAtInput = reservationForm.elements.starts_at;
    const endsAtInput = reservationForm.elements.ends_at;
    const durationInput = reservationForm.elements.duration_minutes;

    function updateReservationEndTime() {
        if (!startsAtInput?.value || !endsAtInput || !durationInput) return;

        const end = new Date(startsAtInput.value);
        end.setMinutes(end.getMinutes() + Number(durationInput.value || reservationForm.dataset.defaultDuration || 60));
        const localEnd = new Date(end.getTime() - end.getTimezoneOffset() * 60000);
        endsAtInput.value = localEnd.toISOString().slice(0, 16);
    }

    startsAtInput?.addEventListener('input', () => {
        updateReservationEndTime();
    });

    durationInput?.addEventListener('change', updateReservationEndTime);
    reservationForm.addEventListener('reset', () => window.setTimeout(updateReservationEndTime, 0));
    updateReservationEndTime();
}

const organizationScanner = document.querySelector('[data-scanner]');

if (organizationScanner) {
    const video = organizationScanner.querySelector('[data-scanner-video]');
    const frame = organizationScanner.querySelector('.scanner-frame');
    const status = organizationScanner.querySelector('[data-scanner-status]');
    const startButton = organizationScanner.querySelector('[data-scanner-start]');
    const uploadInput = organizationScanner.querySelector('[data-scanner-upload]');
    const nextButton = organizationScanner.querySelector('[data-scanner-next]');
    const manualFallbackButton = organizationScanner.querySelector('[data-scanner-manual-fallback]');
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
    const loginMessage = loginForm?.querySelector('[data-login-message]');
    const emailLoginToggle = loginForm?.querySelector('[data-email-login-toggle]');
    const emailLoginPanel = loginForm?.querySelector('[data-email-login-panel]');
    const emailSubmit = loginForm?.querySelector('[data-email-submit]');
    const authLayout = loginForm?.closest('.auth-layout');
    const authWelcome = authLayout?.querySelector('.auth-welcome');
    const scannerEmailInput = organizationScanner.querySelector('[data-scanner-email]');
    const emailInput = emailLoginPanel?.querySelector('input[name="email"]');
    const emailRememberInput = emailLoginPanel?.querySelector('input[name="remember"]');
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
    let frontScanAttempted = false;
    let backScanDetected = false;
    let backScanAttempted = false;
    let scannerFailed = false;
    let manualFallbackEnabled = false;
    let emailLoginMode = false;
    let existingAccountMatched = false;
    let savedContactAutofilled = false;
    let savedEmergencyAutofilled = false;
    let lastIdentityLookupKey = '';
    let identityLookupSequence = 0;
    let identityLookupTimer = null;

    function updateExtractedFieldEditability() {
        const isManualEntry = manualFallbackEnabled;
        const identityIsReadOnly = frontScanDetected && !isManualEntry;
        const contactIsReadOnly = (backScanDetected || savedContactAutofilled) && !isManualEntry;
        const emergencyIsReadOnly = (backScanDetected || savedEmergencyAutofilled) && !isManualEntry;

        [nameInput, courseInput, studentNumberInput].forEach((field) => {
            field?.toggleAttribute('readonly', identityIsReadOnly);
        });
        [contactInput, emergencyInput].forEach((field) => {
            field?.toggleAttribute('readonly', field === contactInput ? contactIsReadOnly : emergencyIsReadOnly);
        });
    }

    function setValidationMessage(message, type = 'error') {
        if (!validationMessage) return;
        validationMessage.className = `scanner-validation-message is-${type}`;
        validationMessage.textContent = message;
    }

    function setLoginMessage(message) {
        if (!loginMessage) return;
        loginMessage.textContent = message;
        loginMessage.hidden = !message;
    }

    function redirectToDashboard(redirectUrl = null) {
        const fallbackUrl = loginForm.dataset.dashboardUrl || '/dashboard';
        const destination = new URL(redirectUrl || fallbackUrl, window.location.origin);
        const safeDestination = destination.origin === window.location.origin
            ? destination
            : new URL(fallbackUrl, window.location.origin);
        window.location.assign(`${safeDestination.pathname}${safeDestination.search}${safeDestination.hash}`);
    }

    async function submitEmailLogin() {
        const formData = new FormData(loginForm);
        formData.delete('remember');
        if (emailRememberInput?.checked) formData.append('remember', '1');

        emailSubmit.disabled = true;
        setLoginMessage('');
        showCveLoader('Signing in to your NPC account…');

        let timeoutId;

        try {
            const controller = new AbortController();
            timeoutId = window.setTimeout(() => controller.abort(), 20000);
            const response = await fetch(loginForm.getAttribute('action') || '/login', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
                signal: controller.signal,
            });
            window.clearTimeout(timeoutId);

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationErrors = Object.values(payload.errors || {}).flat().join(' ');
                const fallbackMessage = response.status === 419
                    ? 'Your sign-in page expired. Reload it and try again.'
                    : response.status === 429
                        ? 'Too many attempts. Wait a minute, then try again.'
                        : response.status >= 500
                            ? 'Sign-in is temporarily unavailable. Please try again shortly.'
                            : 'The sign-in details could not be verified.';
                throw new Error(payload.message || validationErrors || fallbackMessage);
            }

            if (payload.authenticated !== true) {
                throw new Error('Sign-in could not be completed. Please try again.');
            }

            redirectToDashboard(payload.redirect);
        } catch (error) {
            window.clearTimeout(timeoutId);
            const message = error.name === 'AbortError'
                ? 'Sign-in took too long. Please retry.'
                : error instanceof TypeError
                    ? 'The server could not be reached. Check your connection and retry.'
                    : error.message;
            setLoginMessage(message);
            emailSubmit.disabled = false;
            hideCveLoader();
        }
    }

    function normalizeScannerError(error, stage = 'ocr') {
        const name = String(error?.name || '');
        const message = String(error?.message || error || '').toLowerCase();

        if (stage === 'camera') {
            if (['NotAllowedError', 'SecurityError'].includes(name)) {
                return { code: 'CAMERA_PERMISSION_DENIED', message: 'Camera permission was denied. Upload a photo or enter the missing details manually.' };
            }
            if (['NotFoundError', 'DevicesNotFoundError'].includes(name)) {
                return { code: 'CAMERA_NOT_FOUND', message: 'No camera was found. Upload a photo or enter the missing details manually.' };
            }
            if (['NotReadableError', 'TrackStartError'].includes(name)) {
                return { code: 'CAMERA_BUSY', message: 'The camera is busy or unavailable. Close other camera apps, then try again or upload a photo.' };
            }

            return { code: 'CAMERA_START_FAILED', message: 'The camera could not start. Upload a photo or enter the missing details manually.' };
        }

        if (/timeout|took too long|timed out/.test(message)) {
            return { code: 'OCR_TIMEOUT', message: 'The text reader took too long. Try a smaller, clearer photo or enter the missing details manually.' };
        }
        if (/fetch|network|traineddata|wasm|worker|language data|404|load failed/.test(message)) {
            return { code: 'OCR_ASSET_UNAVAILABLE', message: 'The text reader could not load. Check your connection, retry, or enter the missing details manually.' };
        }
        if (/decode|invalid image|unsupported image|image format|blob/.test(message) || name === 'EncodingError') {
            return { code: 'INVALID_IMAGE', message: 'This image could not be opened. Choose a JPG, PNG, or WebP photo and try again.' };
        }
        if (/canvas|security|tainted|image dimensions/.test(message)) {
            return { code: 'IMAGE_PROCESSING_FAILED', message: 'The image could not be prepared for reading. Choose a clear photo or enter the missing details manually.' };
        }

        return { code: 'OCR_FAILED', message: 'The ID could not be read. Try a clear, well-lit photo or enter the missing details manually.' };
    }

    function scannerFieldsComplete() {
        return [nameInput, courseInput, studentNumberInput, contactInput, emergencyInput].every((field) => field?.value.trim());
    }

    function hasManuallyCompletedBackDetails() {
        return (manualFallbackEnabled || backScanAttempted) && Boolean(contactInput?.value.trim() && emergencyInput?.value.trim());
    }

    function updateEmailAccess() {
        const shouldShowCredentials = emailLoginMode;
        emailLoginPanel?.toggleAttribute('hidden', !shouldShowCredentials);
        emailSubmit?.toggleAttribute('hidden', !emailLoginMode);
        if (emailInput) emailInput.required = emailLoginMode;
        if (scannerEmailInput) scannerEmailInput.required = scannerFailed && !existingAccountMatched;
        if (passwordInput) passwordInput.required = emailLoginMode;
        emailRequirement?.toggleAttribute('hidden', !scannerFailed || existingAccountMatched);
    }

    function updateValidationButton() {
        const fieldsFilled = scannerFieldsComplete();
        const backDetailsComplete = backScanDetected || hasManuallyCompletedBackDetails();
        scanComplete = manualFallbackEnabled || (frontScanDetected && (backDetailsComplete || existingAccountMatched));
        const institutionalEmailFilled = Boolean(scannerEmailInput?.value.trim() || emailInput?.value.trim());
        if (validateButton) {
            validateButton.disabled = !(scanComplete && fieldsFilled && (existingAccountMatched || !scannerFailed || institutionalEmailFilled));
            const label = validateButton.firstChild;
            if (label?.nodeType === Node.TEXT_NODE) label.textContent = existingAccountMatched ? 'Continue to sign in ' : 'Validate extracted details ';
        }
        if (manualFallbackButton) manualFallbackButton.hidden = manualFallbackEnabled;
        if (!manualFallbackEnabled && hasManuallyCompletedBackDetails() && !backScanDetected && resultsNote) {
            resultsNote.textContent = 'Back image captured. Review the contact details you entered, then continue with your NPC email.';
        }
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
        const missingChecks = requiredChecks.filter((check) => !check.valid).map((check) => check.label.toLowerCase());
        const sideValid = missingChecks.length === 0;
        resultsNote.textContent = sideValid
            ? `${isFront ? 'Front' : 'Back'} side details found. Confirm the extracted values.`
            : `${isFront ? 'Front' : 'Back'} scan could not confirm ${missingChecks.join(', ')}. Review the image and enter any missing values manually.`;
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
                    const progressMessage = workerStatus === 'recognizing text'
                        ? `Reading ID text${percentage}…`
                        : 'Preparing the text reader…';
                    status.textContent = progressMessage;
                    if (cveLoader && !cveLoader.hidden && cveLoaderMessage) {
                        cveLoaderMessage.textContent = progressMessage;
                    }
                },
            }));
        }

        return ocrWorkerPromise;
    }

    async function prepareOcrImage(source) {
        let drawable = source;
        let objectUrl = null;
        try {
            if (source instanceof Blob) {
                objectUrl = URL.createObjectURL(source);
                drawable = new Image();
                drawable.src = objectUrl;
                await drawable.decode();
            }

            const width = drawable.videoWidth || drawable.naturalWidth || drawable.width;
            const height = drawable.videoHeight || drawable.naturalHeight || drawable.height;
            if (!Number.isFinite(width) || !Number.isFinite(height) || width < 1 || height < 1) {
                throw new Error('Invalid image dimensions.');
            }

            const longestSide = Math.max(width, height);
            const scale = Math.min(1.8, Math.max(0.7, 1800 / longestSide));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(width * scale);
            canvas.height = Math.round(height * scale);
            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) throw new Error('Canvas image processing is unavailable.');
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.filter = 'grayscale(1) contrast(1.35) brightness(1.06)';
            context.drawImage(drawable, 0, 0, canvas.width, canvas.height);
            context.filter = 'none';
            return canvas;
        } finally {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        }
    }

    function cropOcrRegion(canvas, top, height, left = 0, width = 1) {
        const crop = document.createElement('canvas');
        crop.width = Math.max(1, Math.round(canvas.width * width));
        crop.height = Math.max(1, Math.round(canvas.height * height));
        const context = crop.getContext('2d');
        if (!context) throw new Error('Canvas image processing is unavailable.');
        context.drawImage(
            canvas,
            Math.round(canvas.width * left),
            Math.round(canvas.height * top),
            crop.width,
            crop.height,
            0,
            0,
            crop.width,
            crop.height,
        );
        return crop;
    }

    function formatDisplayName(value) {
        return value
            .toLowerCase()
            .replace(/\b([a-z])\./g, (_, initial) => `${initial.toUpperCase()}.`)
            .replace(/(^|[\s'-])([a-z])/g, (_, prefix, initial) => `${prefix}${initial.toUpperCase()}`)
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeStudentNumber(value) {
        const normalized = String(value || '')
            .toUpperCase()
            .replace(/[OQ]/g, '0')
            .replace(/[IL]/g, '1')
            .replace(/S/g, '5')
            .replace(/[BZ]/g, (letter) => letter === 'B' ? '8' : '2')
            .replace(/[^0-9]/g, '');
        return normalized;
    }

    function normalizeOcrName(value) {
        return String(value || '')
            .replace(/\b([58])\./g, 'B.')
            .replace(/^(?:FULL\s+NAME|NAME)\s*[:.-]?\s*/i, '')
            .replace(/^[^A-Za-z]+/, '')
            .replace(/^[A-Z]\s+(?=[A-Z][a-z]+\s)/, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function extractLikelyPersonalName(lines) {
        const candidates = new Map();
        const addCandidate = (value, isWholeLine = false) => {
            const normalized = normalizeOcrName(value);
            if (!isLikelyPersonalName(normalized)) return;
            const candidate = candidates.get(normalized) || { value: normalized, occurrences: 0, wholeLine: false };
            candidate.occurrences += 1;
            candidate.wholeLine = candidate.wholeLine || isWholeLine;
            candidates.set(normalized, candidate);
        };

        lines.forEach((line) => {
            const normalizedLine = normalizeOcrName(line);
            const words = normalizedLine.split(/\s+/).filter(Boolean);
            addCandidate(normalizedLine, true);

            for (let start = 0; start < words.length; start += 1) {
                for (let length = 2; length <= Math.min(5, words.length - start); length += 1) {
                    addCandidate(words.slice(start, start + length).join(' '));
                }
            }
        });

        const rankedCandidates = [...candidates.values()].map((candidate) => {
            const words = candidate.value.split(/\s+/);
            const hasMiddleInitial = words.slice(1, -1).some((word) => /^[A-Z]\.?$/i.test(word));
            const nameLengthScore = words.length === 4 ? 40 : words.length === 3 ? 32 : words.length === 2 ? 24 : 0;
            const score = nameLengthScore
                + (hasMiddleInitial ? 14 : 0)
                + (candidate.wholeLine ? 4 : 0)
                + Math.min(candidate.occurrences - 1, 3) * 3;

            return { ...candidate, score };
        });

        const preferredCandidates = rankedCandidates.filter((candidate) => candidate.score > 0);
        const candidatesToRank = preferredCandidates.length > 0 ? preferredCandidates : rankedCandidates;

        return candidatesToRank.sort((first, second) => second.score - first.score)[0]?.value || '';
    }

    function isValidStudentNumber(value) {
        return /^\d{6}$/.test(normalizeStudentNumber(String(value || '')));
    }

    function extractStudentNumber(value) {
        const text = String(value || '').toUpperCase();
        const candidatesFrom = (candidateText) => [...candidateText.matchAll(/[0-9OQILSBZ](?:[\s-]*[0-9OQILSBZ]){5}/g)]
            .map((match) => normalizeStudentNumber(match[0]))
            .filter((candidate) => /^25\d{4}$/.test(candidate));
        const labeledSections = [...text.matchAll(/(?:STUDENT\s*(?:NO\.?|NUMBER)|STUD\.?\s*NO\.?)[^\r\n]{0,40}(?:\r?\n[^\r\n]{0,40})?/gi)]
            .flatMap((match) => candidatesFrom(match[0]));

        if (labeledSections.length > 0) {
            return labeledSections[0];
        }

        const unlabelledCandidates = [...new Set(candidatesFrom(text))];

        return unlabelledCandidates.length === 1 ? unlabelledCandidates[0] : '';
    }

    function isLikelyPersonalName(value) {
        const normalized = String(value || '').replace(/\s+/g, ' ').trim().toUpperCase();
        const blockedTerms = /(?:\bFULL\s+NAME\b|\bNAME\b|\bSURNAME\b|\bGIVEN\s+NAME\b|\bOV\b|INTEGRATED|COMPUTER|SOCIETY|ORGANIZATION|NAVOTAS|POLYTECHNIC|COLLEGE|NPC|STUD(?:ENT)?|CONTACT|VALIDATION|SIGNATURE|ASSOCIATE|BACHELOR|COURSE|PROGRAM|SYSTEM|INFORMATION|CITY|KAUNLARAN|ADDRESS|STREET|PHILIPPINES|MANILA)/;
        const words = normalized.replace(/[^A-Z .'-]/g, ' ').split(/\s+/).filter(Boolean);
        const fullNameWords = words.filter((word) => word.replace(/\./g, '').length >= 3);

        return /^[A-Z][A-Z .'-]{4,}$/.test(normalized)
            && words.length >= 2
            && words.length <= 6
            && words[0].replace(/\./g, '').length >= 2
            && words[words.length - 1].replace(/\./g, '').length >= 2
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

    function extractContactDetails(text) {
        const normalizedText = String(text || '').replace(/\r/g, '');
        const phonePattern = /(?:^|[^\d])((?:(?:\+?\s*63|00\s*63)[\s().,:/\\|\u2010-\u2014-]*)?0?9(?:[\s().,:/\\|\u2010-\u2014-]*\d){9})(?![\s().,:/\\|\u2010-\u2014-]*\d)/g;
        const normalizePhones = (value) => [...String(value || '')
            .replace(/\r/g, '')
            .replace(/[OoQq]/g, '0')
            .replace(/[IlL]/g, '1')
            .replace(/[Ss]/g, '5')
            .replace(/[Bb]/g, '8')
            .replace(/[Zz]/g, '2')
            .matchAll(phonePattern)]
            .map((match) => {
                let digits = match[1].replace(/\D/g, '');
                if (digits.startsWith('0063')) digits = digits.slice(2);
                if (digits.startsWith('63')) digits = `0${digits.slice(2)}`;
                else if (digits.startsWith('9')) digits = `0${digits}`;

                return /^09\d{9}$/.test(digits) ? digits : '';
            })
            .filter(Boolean);
        const lines = normalizedText.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        const emergencyLineIndex = lines.findIndex((line) => /in\s+case\s+of\s+emergency|emergency\s*(?:contact|person)?|please\s+notify/i.test(line));
        const contactLabelIndex = lines.findIndex((line) => /(?:contact\s*(?:no\.?|number|#)|mobile|cell(?:ular)?|phone|telephone|\btel\b)/i.test(line)
            && !/emergency|notify|contact\s+person/i.test(line));
        const afterEmergency = emergencyLineIndex >= 0 ? lines.slice(emergencyLineIndex).join('\n') : '';
        const emergencyPhone = normalizePhones(afterEmergency)[0] || '';
        const labeledContactText = contactLabelIndex >= 0 ? lines.slice(contactLabelIndex, contactLabelIndex + 4).join('\n') : '';
        const allPhoneCandidates = normalizePhones(normalizedText);
        const contactCandidates = [...normalizePhones(labeledContactText), ...allPhoneCandidates];
        const contact = [...new Set(contactCandidates)].find((candidate) => candidate !== emergencyPhone) || '';
        const emergencyLines = afterEmergency
            .replace(phonePattern, ' ')
            .replace(/in\s+case\s+of\s+emergency|emergency\s*(?:contact|person)?|please\s+notify|contact\s+person/gi, ' ')
            .split(/\r?\n/)
            .map((line) => line.replace(/[|:]/g, ' ').replace(/\s+/g, ' ').trim())
            .filter(Boolean);
        const emergencyName = emergencyLines.find((line) => {
            const words = line.replace(/[^A-Za-z ]/g, ' ').split(/\s+/).filter(Boolean);
            return words.length >= 2 && words.join('').length >= 6 && !/validation|1st|2nd|sem/i.test(line);
        }) || '';

        return {
            contact,
            emergency: [emergencyName, emergencyPhone].filter(Boolean).join(' · '),
        };
    }

    function previewRecognizedFields(text, side) {
        const lines = text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        const extractedName = extractLikelyPersonalName(lines);
        const course = normalizeCourse(
            readField(lines, /^(?:course|program)\s*[:.-]?\s*/i)
                || lines.find((line) => /associate|bachelor|computer|information\s+system/i.test(line) && line.length < 100),
            lines,
        );
        const studentNumber = extractStudentNumber(text);
        const contacts = extractContactDetails(text);
        const validation = /validation|1st\s*sem|2nd\s*sem|signature/i.test(text);

        if (side === 'front') {
            if (extractedName && nameInput) nameInput.value = formatDisplayName(extractedName);
            if (course && courseInput) courseInput.value = course;
            if (isValidStudentNumber(studentNumber) && studentNumberInput) studentNumberInput.value = studentNumber;
        } else {
        }
        if (contacts.contact && contactInput && (!existingAccountMatched || !contactInput.value.trim())) contactInput.value = contacts.contact;
        if (side === 'back' && contacts.emergency && emergencyInput && (!existingAccountMatched || !emergencyInput.value.trim())) emergencyInput.value = contacts.emergency;
        const resolvedContact = contacts.contact || (side === 'back' && existingAccountMatched ? contactInput?.value.trim() : '');
        const resolvedEmergency = contacts.emergency || (side === 'back' && existingAccountMatched ? emergencyInput?.value.trim() : '');

        results.hidden = false;
        resultsNote.textContent = 'Live preview · checking the extracted details against the ID…';

        return side === 'front'
            ? { complete: Boolean(extractedName && course && isValidStudentNumber(studentNumber)), name: Boolean(extractedName), studentNumber: isValidStudentNumber(studentNumber) }
            : { complete: Boolean(resolvedContact && resolvedEmergency && validation), contact: Boolean(resolvedContact), emergency: Boolean(resolvedEmergency), validation };
    }

    async function lookupExistingStudent() {
        const scannedName = nameInput?.value.trim() || '';
        const scannedCourse = courseInput?.value.trim() || '';
        const scannedStudentNumber = normalizeStudentNumber(studentNumberInput?.value || '');
        if (!scannedName || !scannedCourse || !isValidStudentNumber(scannedStudentNumber)) return;

        const lookupKey = [scannedName, scannedCourse, scannedStudentNumber].join('|');
        if (lookupKey === lastIdentityLookupKey) return;
        lastIdentityLookupKey = lookupKey;
        const lookupSequence = ++identityLookupSequence;
        existingAccountMatched = false;
        updateExtractedFieldEditability();
        updateValidationButton();

        try {
            const response = await fetch(loginForm.dataset.lookupUrl || '/login/lookup-id', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': loginForm.querySelector('input[name="_token"]').value },
                body: JSON.stringify({ scanned_name: scannedName, scanned_course: scannedCourse, scanned_student_number: scannedStudentNumber }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Saved account details could not be checked.');
            if (lookupSequence !== identityLookupSequence
                || [nameInput?.value.trim() || '', courseInput?.value.trim() || '', normalizeStudentNumber(studentNumberInput?.value || '')].join('|') !== lookupKey) return;
            if (payload.matched !== true) {
                if (resultsNote) resultsNote.textContent = 'No saved account matched these details. Continue scanning both sides to create an account.';
                return;
            }

            existingAccountMatched = true;
            updateExtractedFieldEditability();
            if (!contactInput?.value.trim() && payload.contact_number) {
                contactInput.value = payload.contact_number;
                savedContactAutofilled = true;
            }
            if (!emergencyInput?.value.trim() && payload.emergency_contact) {
                emergencyInput.value = payload.emergency_contact;
                savedEmergencyAutofilled = true;
            }
            updateExtractedFieldEditability();
            if (resultsNote) {
                resultsNote.textContent = 'Existing account matched. Saved contact details filled in. Scan the back, then continue to sign in with your NPC email and password.';
            }
        } catch {
            if (lookupSequence === identityLookupSequence) lastIdentityLookupKey = '';
            if (resultsNote) resultsNote.textContent = 'Saved account lookup unavailable. Continue scanning the back of your ID; details can still be read from it.';
        } finally {
            updateValidationButton();
        }
    }

    async function processIdImage(source) {
        if (isOcrProcessing) {
            if (status) status.textContent = 'The previous scan is still running. Wait for it to finish, then try again.';
            return;
        }
        const scannedSide = scanSide;
        if (scannedSide === 'front') frontScanAttempted = true;
        if (scannedSide === 'back') backScanAttempted = true;
        if (scannedSide === 'front') {
            [nameInput, courseInput, studentNumberInput].forEach((field) => {
                if (field) field.value = '';
            });
        }
        if (status) status.textContent = 'Reading the ID… this may take a few seconds.';
        isOcrProcessing = true;
        showCveLoader('Preparing your ID scan…');

        try {
            await yieldToBrowser();
            const [worker, preparedImage] = await Promise.all([
                withTimeout(getOcrWorker(), 20000, 'The OCR engine took too long to start.'),
                prepareOcrImage(source),
            ]);
            let passCount = 0;
            const recognize = async (image) => {
                passCount += 1;
                const progressMessage = `Reading ID text · scan ${passCount}…`;
                if (status) status.textContent = progressMessage;
                if (cveLoader && !cveLoader.hidden && cveLoaderMessage) {
                    cveLoaderMessage.textContent = progressMessage;
                }
                await yieldToBrowser();
                return withTimeout(worker.recognize(image), 30000, 'The OCR engine took too long to read this image.');
            };
            const firstPass = await recognize(preparedImage);
            let text = firstPass.data.text || '';
            let preview = previewRecognizedFields(text, scannedSide);
            const previewMessage = 'Live text preview ready · checking details…';
            if (status) status.textContent = previewMessage;
            if (cveLoader && !cveLoader.hidden && cveLoaderMessage) {
                cveLoaderMessage.textContent = previewMessage;
            }
            let focusedNameText = '';
            let focusedNumberText = '';
            if (scannedSide === 'front') {
                if (!preview.complete) {
                    await worker.setParameters({ tessedit_pageseg_mode: '6', tessedit_char_whitelist: '', preserve_interword_spaces: '1' });
                    const focusedPass = await recognize(cropOcrRegion(preparedImage, 0.48, 0.42));
                    text = `${text}\n${focusedPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                await worker.setParameters({ tessedit_pageseg_mode: '7', tessedit_char_whitelist: "ABCDEFGHIJKLMNOPQRSTUVWXYZ .-'" });
                try {
                    const namePass = await recognize(cropOcrRegion(preparedImage, 0.69, 0.075, 0.23, 0.62));
                    focusedNameText = namePass.data.text || '';
                    if (focusedNameText) {
                        text = `${text}\n${focusedNameText}`;
                        preview = previewRecognizedFields(text, scannedSide);
                    }
                } catch {
                    focusedNameText = '';
                }

                await worker.setParameters({ tessedit_char_whitelist: '0123456789-', tessedit_pageseg_mode: '7' });
                try {
                    const numberPass = await recognize(cropOcrRegion(preparedImage, 0.755, 0.095));
                    focusedNumberText = numberPass.data.text || '';
                    if (focusedNumberText) {
                        text = `${text}\n${focusedNumberText}`;
                        preview = previewRecognizedFields(text, scannedSide);
                    }
                } catch {
                    focusedNumberText = '';
                }
            } else if (!preview.complete) {
                if (!preview.contact) {
                    await worker.setParameters({ tessedit_pageseg_mode: '6', preserve_interword_spaces: '1', tessedit_char_whitelist: '' });
                    const contactLinePass = await recognize(cropOcrRegion(preparedImage, 0.24, 0.28, 0.1, 0.8));
                    text = `${text}\n${contactLinePass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.contact) {
                    await worker.setParameters({ tessedit_pageseg_mode: '7', tessedit_char_whitelist: '0123456789OQILSBZ' });
                    const contactDigitsPass = await recognize(cropOcrRegion(preparedImage, 0.32, 0.06, 0.18, 0.68));
                    text = `${text}\n${contactDigitsPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.emergency || !preview.validation) {
                    await worker.setParameters({ tessedit_pageseg_mode: '6', preserve_interword_spaces: '1', tessedit_char_whitelist: '' });
                    const focusedPass = await recognize(cropOcrRegion(preparedImage, 0.3, 0.68));
                    text = `${text}\n${focusedPass.data.text || ''}`;
                    preview = previewRecognizedFields(text, scannedSide);
                }

                if (!preview.contact || !preview.emergency) {
                    await worker.setParameters({ tessedit_char_whitelist: '0123456789+()-. OQILSBZ', tessedit_pageseg_mode: '11' });
                    for (const [top, height] of [[0.02, 0.58], [0.4, 0.58]]) {
                        const contactPass = await recognize(cropOcrRegion(preparedImage, top, height));
                        text = `${text}\n${contactPass.data.text || ''}`;
                        preview = previewRecognizedFields(text, scannedSide);
                        if (preview.contact && preview.emergency) break;
                    }
                }
            }
            await worker.setParameters({ tessedit_pageseg_mode: '3', tessedit_char_whitelist: '' });
            const lines = text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            const studentNumber = extractStudentNumber(focusedNumberText) || extractStudentNumber(text);
            const contacts = extractContactDetails(text);
            const contact = contacts.contact;
            const course = normalizeCourse(
                readField(lines, /^(?:course|program)\s*[:.-]?\s*/i)
                    || lines.find((line) => /associate|bachelor|computer|information\s+system/i.test(line) && line.length < 100),
                lines,
            );
            const focusedNameLines = focusedNameText.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            const extractedName = extractLikelyPersonalName([...focusedNameLines, ...lines]);
            const emergency = contacts.emergency || lines.find((line) => /emergency|notify|contact\s+person/i.test(line)) || '';
            const validationFound = /validation|1st\s*sem|2nd\s*sem|signature/i.test(text);

            if (scannedSide === 'front') {
                if (isLikelyPersonalName(extractedName)) nameInput.value = formatDisplayName(extractedName);
                if (course) courseInput.value = course;
                if (isValidStudentNumber(studentNumber)) studentNumberInput.value = studentNumber;
                if (/integrated computer society|\bics\b/i.test(text)) input.value = 'ICS';
            }
            const resolvedContact = contact || (existingAccountMatched ? contactInput?.value.trim() : '');
            const resolvedEmergency = emergency || (existingAccountMatched ? emergencyInput?.value.trim() : '');
            if (contact && (!existingAccountMatched || !contactInput?.value.trim())) contactInput.value = contact;
            if (scannedSide === 'back' && emergency && (!existingAccountMatched || !emergencyInput?.value.trim())) emergencyInput.value = emergency;
            results.hidden = false;
            const sideValid = validateScannedId(text, extractedName, scannedSide, {
                course,
                studentNumber,
                contact: resolvedContact,
                emergency: resolvedEmergency,
                validation: validationFound,
            });
            delete organizationScanner.dataset.errorCode;
            if (scannedSide === 'front') frontScanDetected = sideValid;
            else backScanDetected = sideValid;
            scannerFailed = scannedSide === 'front' ? !sideValid : (scannerFailed || !sideValid);
            updateExtractedFieldEditability();
            if (scannedSide === 'front') {
                if (sideValid) {
                    scanSide = 'back';
                    updateStageUi();
                    if (status) status.textContent = 'Front captured. Turn the ID over, then scan the back.';
                    await lookupExistingStudent();
                } else if (status) {
                    status.textContent = 'Front scan incomplete. Fill the missing fields, then scan the back ID.';
                    updateStageUi();
                }
            } else if (status) {
                scanComplete = frontScanDetected && backScanDetected;
                status.textContent = scanComplete
                    ? 'Front and back scanned. Review the extracted details before continuing.'
                    : `Back scan incomplete. ${resolvedContact ? 'Review the contact number and fill other missing details.' : 'The contact number was not readable; enter it manually, then complete the other highlighted details.'}`;
            }
            updateValidationButton();
            if (existingAccountMatched && resultsNote) {
                resultsNote.textContent = 'Existing account matched. Saved contact details are filled where available; continue to sign in with your NPC email and password.';
            }
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
            updateExtractedFieldEditability();
            updateEmailAccess();
            const failure = normalizeScannerError(error);
            organizationScanner.dataset.errorCode = failure.code;
            if (status) status.textContent = failure.message;
            if (resultStatus) {
                resultStatus.hidden = false;
                resultStatus.className = 'scanner-result-status is-error';
                resultStatus.textContent = `× ${failure.message}`;
            }
            console.error(`[ID scanner] ${failure.code}`);
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
        } catch (error) {
            await stopOrganizationScanner();
            hideCveLoader();
            const failure = normalizeScannerError(error, 'camera');
            organizationScanner.dataset.errorCode = failure.code;
            if (status) status.textContent = failure.message;
        }
    }

    function warmOcrWorker() {
        void getOcrWorker().catch((error) => {
            ocrWorkerPromise = null;
            const failure = normalizeScannerError(error);
            organizationScanner.dataset.errorCode = failure.code;
            if (status) status.textContent = failure.message;
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

    nextButton?.addEventListener('click', async () => {
        if (!nameInput?.value.trim() || !courseInput?.value.trim() || !studentNumberInput?.value.trim()) {
            if (validationMessage) validationMessage.textContent = 'Fill full name, course, and student number before moving to the back ID.';
            return;
        }
        frontScanDetected = true;
        updateExtractedFieldEditability();
        scanSide = 'back';
        updateStageUi();
        if (status) status.textContent = 'Front details saved. Turn the ID over, then scan the back.';
        await lookupExistingStudent();
        if (resultStatus) {
            resultStatus.hidden = false;
            resultStatus.className = 'scanner-result-status is-success';
            resultStatus.textContent = '✓ Front details confirmed manually. Back side is required.';
        }
        updateValidationButton();
    });

    manualFallbackButton?.addEventListener('click', () => {
        if (!frontScanAttempted || !backScanAttempted) {
            setValidationMessage('Capture both sides of your NPC ID first. If text is unreadable, you can then enter the details manually.');
            return;
        }
        manualFallbackEnabled = true;
        scannerFailed = true;
        frontScanDetected = false;
        backScanDetected = false;
        backScanAttempted = true;
        results.hidden = false;
        updateExtractedFieldEditability();
        if (status) status.textContent = 'Manual entry enabled. Enter the details exactly as printed and provide your NPC institutional email.';
        if (resultStatus) {
            resultStatus.hidden = false;
            resultStatus.className = 'scanner-result-status is-error';
            resultStatus.textContent = 'Scanning skipped. Review every field and enter your NPC email to continue.';
        }
        if (resultsNote) resultsNote.textContent = 'Manual fallback · enter the details exactly as printed on both ID sides.';
        updateValidationButton();
        nameInput?.focus();
    });

    [nameInput, courseInput, studentNumberInput].forEach((field) => field?.addEventListener('input', () => {
        identityLookupSequence += 1;
        lastIdentityLookupKey = '';
        existingAccountMatched = false;
        if (savedContactAutofilled && contactInput) contactInput.value = '';
        if (savedEmergencyAutofilled && emergencyInput) emergencyInput.value = '';
        savedContactAutofilled = false;
        savedEmergencyAutofilled = false;
        updateExtractedFieldEditability();
        updateValidationButton();
        if (scanSide === 'back' && frontScanDetected) {
            window.clearTimeout(identityLookupTimer);
            identityLookupTimer = window.setTimeout(() => lookupExistingStudent(), 350);
        }
    }));
    [contactInput, emergencyInput].forEach((field) => field?.addEventListener('input', () => {
        if (field === contactInput) savedContactAutofilled = false;
        if (field === emergencyInput) savedEmergencyAutofilled = false;
        updateValidationButton();
    }));
    [scannerEmailInput, emailInput].forEach((field) => field?.addEventListener('input', updateValidationButton));
    [signatureInput, organizationConfirmedInput, emergencyConfirmedInput, validationConfirmedInput, confirmInput].forEach((field) => field?.addEventListener('change', updateValidationButton));
    emailLoginToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        emailLoginMode = !emailLoginMode;
        emailLoginToggle.textContent = emailLoginMode ? 'Use organization ID scanner' : 'Sign in by Institutional Email';
        updateEmailAccess();
        if (emailLoginMode) emailInput?.focus();
    });
    loginForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!emailLoginMode) {
            hideCveLoader();
            emailLoginMode = true;
            emailLoginToggle.textContent = 'Use organization ID scanner';
            updateEmailAccess();
            emailSubmit.disabled = false;
            emailInput?.focus();
            return;
        }

        await submitEmailLogin();
    });
    validateButton?.addEventListener('click', async () => {
        updateValidationButton();
        if (validateButton.disabled) {
            setValidationMessage(existingAccountMatched
                ? 'Complete the front ID details and saved contact fields first.'
                : 'Scan both sides of the ID and complete the required details first.');
            return;
        }
        if (existingAccountMatched) {
            if (!frontScanDetected || !scannerFieldsComplete()) {
                setValidationMessage('Complete the front ID details and saved contact fields first.');
                return;
            }

            emailLoginMode = true;
            emailLoginToggle.textContent = 'Use organization ID scanner';
            updateEmailAccess();
            setValidationMessage('Existing student matched. Sign in with your NPC institutional email and password.', 'success');
            emailInput?.focus();
            emailLoginPanel?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const missingFields = [
            [nameInput, 'full name'],
            [courseInput, 'course'],
            [studentNumberInput, 'student number'],
            [contactInput, 'contact number'],
            [emergencyInput, 'emergency contact'],
        ].filter(([field]) => !field?.value.trim()).map(([, label]) => label);
        const manuallyCompletedBack = hasManuallyCompletedBackDetails();
        if (missingFields.length || (!manualFallbackEnabled && !frontScanDetected) || (!manualFallbackEnabled && !backScanDetected && !manuallyCompletedBack)) {
            const missing = [...missingFields];
            if (!manualFallbackEnabled && !frontScanDetected) missing.unshift('front ID scan');
            if (!manualFallbackEnabled && !backScanDetected && !manuallyCompletedBack) missing.unshift('back ID scan');
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
        let timeoutId;
        try {
            const controller = new AbortController();
            timeoutId = window.setTimeout(() => controller.abort(), 20000);
            const response = await fetch(loginForm.dataset.verifyUrl || '/login/verify-id', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': loginForm.querySelector('input[name="_token"]').value },
                body: JSON.stringify(Object.fromEntries(new FormData(loginForm))),
                signal: controller.signal,
            });
            window.clearTimeout(timeoutId);
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationErrors = Object.values(payload.errors || {}).flat().join(' ');
                const fallbackMessage = response.status === 419
                    ? 'Your page session expired. Reload the page and try again; your details are still on this page.'
                    : response.status === 429
                        ? 'Too many attempts. Wait a minute, then retry; your details are still on this page.'
                        : response.status >= 500
                            ? 'Account verification is temporarily unavailable. Your details are still here; retry shortly or sign in with your institutional email.'
                            : 'The ID details could not be validated.';
                throw new Error(payload.message || validationErrors || fallbackMessage);
            }
            setValidationMessage('Validated successfully. Opening the dashboard…', 'success');
            if (payload.authenticated !== true) throw new Error('Verification completed without starting your session. Please retry.');
            redirectToDashboard(payload.redirect);
        } catch (error) {
            window.clearTimeout(timeoutId);
            const message = error.name === 'AbortError'
                ? 'Verification took too long. Your details are still here; retry shortly or sign in with your institutional email.'
                : error instanceof TypeError
                    ? 'The server could not be reached. Check your connection and retry; your details are still here.'
                    : error.message;
            setValidationMessage(`Validation failed: ${message}`);
            validateButton.disabled = false;
            hideCveLoader();
        }
    });

    window.addEventListener('pagehide', stopOrganizationScanner);
}

if (reservationForm) {
    const message = reservationForm.querySelector('[data-reservation-message]');
    reservationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = reservationForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        showCveLoader('Checking room availability…');
        message.textContent = 'Checking availability…';
        message.className = 'form-message';

        try {
            const response = await fetch(reservationForm.dataset.endpoint, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': reservationForm.querySelector('input[name="_token"]').value },
                body: JSON.stringify(Object.fromEntries(new FormData(reservationForm))),
            });
            const payload = await readJsonResponse(response);
            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'Reservation could not be submitted.');
                throw new Error(errors);
            }
            reservationForm.reset();
            message.textContent = 'Reservation request submitted successfully.';
            message.classList.add('is-success');
        } catch (error) {
            message.textContent = error.message;
            message.classList.add('is-error');
        } finally {
            submit.disabled = false;
            hideCveLoader();
        }
    });
}

const sidebarToggle = document.querySelector('#sidebar-toggle');
const shell = document.querySelector('#app-shell');
const mobileScrim = document.querySelector('#mobile-scrim');

if (sidebarToggle && shell && mobileScrim) {
    const sidebarPreferenceKey = 'cve.sidebarCollapsed';
    const isMobileNavigation = () => window.matchMedia('(max-width: 680px)').matches;

    function applyDesktopSidebarPreference() {
        if (isMobileNavigation()) {
            return;
        }

        let collapsed = false;
        try {
            collapsed = window.localStorage.getItem(sidebarPreferenceKey) === 'true';
        } catch {}

        shell.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
    }

    function setMobileNavigation(open) {
        shell.classList.toggle('mobile-nav-open', open);
        mobileScrim.hidden = !open;
        sidebarToggle.setAttribute('aria-expanded', String(open));
        sidebarToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        sidebarToggle.title = open ? 'Close navigation' : 'Open navigation';
    }

    sidebarToggle.addEventListener('click', () => {
        if (isMobileNavigation()) {
            setMobileNavigation(!shell.classList.contains('mobile-nav-open'));
        } else {
            const collapsed = shell.classList.toggle('sidebar-collapsed');
            try {
                window.localStorage.setItem(sidebarPreferenceKey, String(collapsed));
            } catch {}
            sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
            sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
        }
    });

    mobileScrim.addEventListener('click', () => setMobileNavigation(false));
    document.querySelectorAll('.sidebar a').forEach((link) => {
        link.addEventListener('click', () => {
            if (isMobileNavigation()) {
                setMobileNavigation(false);
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMobileNavigation(false);
        }
    });

    if (isMobileNavigation()) {
        setMobileNavigation(false);
    } else {
        applyDesktopSidebarPreference();
    }

    window.addEventListener('resize', () => {
        if (isMobileNavigation()) {
            setMobileNavigation(false);
        } else {
            applyDesktopSidebarPreference();
        }
    });

    const sidebarTooltip = document.createElement('div');
    sidebarTooltip.className = 'sidebar-link-tooltip';
    sidebarTooltip.id = 'sidebar-link-tooltip';
    sidebarTooltip.setAttribute('role', 'tooltip');
    sidebarTooltip.hidden = true;
    document.body.append(sidebarTooltip);

    let tooltipTarget = null;

    function hideSidebarTooltip() {
        if (tooltipTarget) {
            tooltipTarget.removeAttribute('aria-describedby');
            tooltipTarget = null;
        }

        sidebarTooltip.hidden = true;
    }

    function showSidebarTooltip(link) {
        const label = link.dataset.sidebarTooltip;
        if (!label || !shell.classList.contains('sidebar-collapsed') || isMobileNavigation()) {
            hideSidebarTooltip();
            return;
        }

        const linkBounds = link.getBoundingClientRect();
        sidebarTooltip.textContent = label;
        sidebarTooltip.style.top = `${Math.min(window.innerHeight - 20, Math.max(20, linkBounds.top + linkBounds.height / 2))}px`;
        sidebarTooltip.style.left = `${linkBounds.right + 9}px`;
        sidebarTooltip.hidden = false;
        if (tooltipTarget && tooltipTarget !== link) {
            tooltipTarget.removeAttribute('aria-describedby');
        }

        tooltipTarget = link;
        link.setAttribute('aria-describedby', sidebarTooltip.id);
    }

    document.querySelectorAll('.sidebar [data-sidebar-tooltip]').forEach((link) => {
        link.addEventListener('pointerenter', () => showSidebarTooltip(link));
        link.addEventListener('pointerleave', () => {
            if (document.activeElement !== link) {
                hideSidebarTooltip();
            }
        });
        link.addEventListener('focus', () => showSidebarTooltip(link));
        link.addEventListener('blur', hideSidebarTooltip);
    });
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
        empty.className = 'availability-result-message is-empty';
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
        empty.className = 'upcoming-empty schedule-skeleton-empty';
        empty.setAttribute('aria-label', 'No reservations scheduled. Placeholder timeline rows shown for layout preview.');
        const message = document.createElement('strong');
        message.textContent = 'No reservations scheduled for this floor on this date.';
        const skeleton = document.createElement('span');
        skeleton.className = 'schedule-skeleton-rows';
        skeleton.setAttribute('aria-hidden', 'true');
        for (let index = 0; index < 3; index += 1) {
            const row = document.createElement('i');
            skeleton.append(row);
        }
        empty.append(message, skeleton);
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
    if (messageElement) {
        messageElement.textContent = 'Loading live room data…';
        messageElement.classList.remove('is-error');
    }
    showCveLoader('Refreshing live room data…');
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const payload = await readJsonResponse(response);
        if (!response.ok) throw new Error(payload.message || 'Live room data could not be loaded.');
        onSuccess(payload);
        if (messageElement) messageElement.textContent = `Updated ${new Date(payload.meta.generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}.`;
    } catch (error) {
        if (messageElement) {
            messageElement.textContent = error.message || 'Live room data could not be loaded. Try again shortly.';
            messageElement.classList.add('is-error');
        }
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
    let rooms = [];
    let requestId = 0;

    function setDirectoryMessage(message, state = '') {
        let statusText = directoryMessage.querySelector('span');
        if (!statusText) {
            statusText = document.createElement('span');
            directoryMessage.append(statusText);
        }
        statusText.textContent = message;
        directoryMessage.classList.toggle('is-error', state === 'error');
        directoryMessage.classList.toggle('is-empty', state === 'empty');
    }

    function renderDirectory() {
        const visibleRooms = rooms;
        directoryGrid.replaceChildren();

        visibleRooms.forEach((room) => {
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
                ['Floor', room.floorName],
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
            floorPlanUrl.searchParams.set('floor', room.floor);
            floorPlanLink.href = floorPlanUrl.toString();

            const scheduleLink = document.createElement('a');
            scheduleLink.className = 'text-link';
            scheduleLink.href = directoryGrid.dataset.scheduleUrl;
            scheduleLink.textContent = 'View schedule';
            actions.append(floorPlanLink);

            if (directoryGrid.dataset.reservationsUrl) {
                const reservationLink = document.createElement('a');
                reservationLink.className = 'secondary-button';
                reservationLink.href = directoryGrid.dataset.reservationsUrl;
                reservationLink.textContent = 'Reserve Room';
                const reservationUrl = new URL(directoryGrid.dataset.reservationsUrl, window.location.origin);
                reservationUrl.searchParams.set('room_id', room.reservationId);
                reservationLink.href = reservationUrl.toString();
                actions.append(reservationLink);
            }

            actions.append(scheduleLink);

            card.append(top, facts, actions);
            directoryGrid.append(card);
        });

        const isEmpty = visibleRooms.length === 0;
        setDirectoryMessage(isEmpty
            ? 'No rooms match the selected filters on this floor.'
            : `${visibleRooms.length} room${visibleRooms.length === 1 ? '' : 's'} shown for this floor.`, isEmpty ? 'empty' : '');
    }

    function updateFilterOptions(meta) {
        const selectedFloor = String(meta.floor || '');
        const selectedType = typeSelect.value;
        const floors = meta.floors || [];
        const types = meta.types || [];
        const resetType = selectedType !== 'all' && !types.includes(selectedType);

        floorSelect.replaceChildren(new Option('Choose a floor', ''));
        floors.forEach((floor) => floorSelect.add(new Option(floor.name, floor.number)));
        floorSelect.disabled = floors.length === 0;
        floorSelect.value = selectedFloor;

        typeSelect.replaceChildren(new Option('All room types', 'all'));
        types.forEach((type) => typeSelect.add(new Option(type, type)));
        typeSelect.value = types.includes(selectedType) ? selectedType : 'all';

        return resetType;
    }

    async function loadDirectory() {
        const activeRequestId = ++requestId;
        setDirectoryMessage('Loading room inventory…');
        directoryGrid.setAttribute('aria-busy', 'true');
        showCveLoader('Loading room inventory…');

        try {
            const url = new URL(directoryMessage.dataset.endpoint, window.location.origin);
            if (floorSelect.value) {
                url.searchParams.set('floor', floorSelect.value);
            }
            if (typeSelect?.value && typeSelect.value !== 'all') {
                url.searchParams.set('type', typeSelect.value);
            }
            if (capacityInput?.value) {
                url.searchParams.set('min_capacity', capacityInput.value);
            }
            if (statusSelect?.value && statusSelect.value !== 'all') {
                url.searchParams.set('status', statusSelect.value);
            }
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await readJsonResponse(response);
            if (!response.ok) {
                throw new Error(payload.message || `Room directory request failed with status ${response.status}.`);
            }
            if (!Array.isArray(payload.data) || activeRequestId !== requestId) {
                return;
            }

            const shouldReloadAfterTypeReset = updateFilterOptions(payload.meta || {});
            if (shouldReloadAfterTypeReset) {
                loadDirectory();
                return;
            }

            rooms = payload.data;
            renderDirectory();
        } catch (error) {
            if (activeRequestId !== requestId) {
                return;
            }

            rooms = [];
            directoryGrid.replaceChildren();
            setDirectoryMessage(error.message || 'Room inventory could not be loaded. Check your connection and try again.', 'error');
            console.error(error);
        } finally {
            if (activeRequestId === requestId) {
                directoryGrid.setAttribute('aria-busy', 'false');
                hideCveLoader();
            }
        }
    }

    floorSelect.addEventListener('change', loadDirectory);
    statusSelect.addEventListener('change', loadDirectory);
    typeSelect.addEventListener('change', loadDirectory);
    capacityInput.addEventListener('change', loadDirectory);
    loadDirectory();
}

document.querySelectorAll('[data-equipment-form-toggle]').forEach((toggle) => {
    const formPanel = document.getElementById(toggle.getAttribute('aria-controls') || '');
    if (!formPanel) return;
    toggle.addEventListener('click', () => {
        const selectedEquipmentId = toggle.dataset.equipmentId;
        const equipmentSelect = formPanel.querySelector('[name="equipment_id"]');
        if (selectedEquipmentId && equipmentSelect) equipmentSelect.value = selectedEquipmentId;
        const opening = Boolean(selectedEquipmentId) || formPanel.hidden;
        formPanel.hidden = !opening;
        document.querySelectorAll('[data-equipment-form-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', String(opening));
        });
        document.querySelectorAll('[data-equipment-form-close]').forEach((button) => {
            button.innerHTML = opening ? 'Close request form' : 'Open request form';
        });
        if (opening) {
            formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            (selectedEquipmentId ? equipmentSelect : formPanel.querySelector('select'))?.focus({ preventScroll: true });
        }
    });
});

document.querySelectorAll('[data-qr-code]').forEach(async (qrContainer) => {
    const token = qrContainer.dataset.qrValue;
    const image = qrContainer.querySelector('img');
    const loading = qrContainer.querySelector('.qr-loading');
    const error = qrContainer.querySelector('.qr-error');
    const downloadButton = qrContainer.parentElement.querySelector('[data-qr-download]');
    if (!token || !image || !downloadButton) return;

    try {
        const qrModule = await import('qrcode');
        const qrCode = qrModule.default || qrModule;
        image.src = await qrCode.toDataURL(token, {
            errorCorrectionLevel: 'M',
            width: 280,
            margin: 2,
            color: { dark: '#172c36', light: '#ffffff' },
        });
        image.hidden = false;
        loading?.remove();
        downloadButton.disabled = false;
        downloadButton.addEventListener('click', () => {
            const link = document.createElement('a');
            link.href = image.src;
            link.download = 'campus-profile-qr.png';
            link.click();
        });
    } catch {
        if (loading) loading.hidden = true;
        if (error) error.hidden = false;
    }
});
