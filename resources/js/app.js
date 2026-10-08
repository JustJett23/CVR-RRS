const canvas = document.querySelector('#floor-plan');
const mapStage = document.querySelector('#map-stage');

if (canvas && mapStage) {
    const context = canvas.getContext('2d');
    const tooltip = document.querySelector('#map-tooltip');
    const detailCard = document.querySelector('#room-detail');
    const roomList = document.querySelector('#room-status-list');
    const statusSelect = document.querySelector('#status-select');
    const floorSelect = document.querySelector('#floor-select');
    const floorLabel = document.querySelector('#map-floor-label');
    const sidebarToggle = document.querySelector('#sidebar-toggle');
    const shell = document.querySelector('#app-shell');
    const mobileScrim = document.querySelector('#mobile-scrim');
    const designTokens = getComputedStyle(document.documentElement);
    const colorToken = (name) => designTokens.getPropertyValue(name).trim();

    let floorRooms = { 1: [], 2: [] };
    let roomRequestId = 0;

    const statusColors = {
        available: { fill: colorToken('--map-status-available'), side: colorToken('--map-status-available-side'), label: colorToken('--color-on-primary'), text: 'Available' },
        occupied: { fill: colorToken('--map-status-occupied'), side: colorToken('--map-status-occupied-side'), label: colorToken('--color-on-primary'), text: 'Occupied' },
        reserved: { fill: colorToken('--map-status-reserved'), side: colorToken('--map-status-reserved-side'), label: colorToken('--color-on-primary'), text: 'Reserved' },
        maintenance: { fill: colorToken('--map-status-maintenance'), side: colorToken('--map-status-maintenance-side'), label: colorToken('--color-on-primary'), text: 'Maintenance' },
        unavailable: { fill: colorToken('--map-status-unavailable'), side: colorToken('--map-status-unavailable-side'), label: colorToken('--color-on-primary'), text: 'Unavailable' },
    };

    const state = {
        floor: Number(floorSelect.value || 2),
        filter: 'all',
        yaw: -0.55,
        pitch: 0.67,
        zoom: 1,
        selectedRoom: null,
        hoveredRoom: null,
        pointer: null,
        dragging: false,
        lastPointer: null,
        roomPolygons: new Map(),
    };

    const rooms = () => floorRooms[state.floor] ?? [];

    function project(point, scale, centerX, centerY) {
        const cosine = Math.cos(state.yaw);
        const sine = Math.sin(state.yaw);
        const rotatedX = point.x * cosine - point.z * sine;
        const rotatedZ = point.x * sine + point.z * cosine;
        const screenY = rotatedZ * Math.sin(state.pitch) - point.y * Math.cos(state.pitch);

        return { x: centerX + rotatedX * scale, y: centerY + screenY * scale };
    }

    function drawPolygon(points, fill, stroke = 'transparent', lineWidth = 1) {
        if (points.length < 3) {
            return;
        }

        context.beginPath();
        context.moveTo(points[0].x, points[0].y);
        points.slice(1).forEach((point) => context.lineTo(point.x, point.y));
        context.closePath();
        context.fillStyle = fill;
        context.fill();

        if (stroke !== 'transparent') {
            context.strokeStyle = stroke;
            context.lineWidth = lineWidth;
            context.stroke();
        }
    }

    function boxFaces(x, z, width, depth, height, scale, centerX, centerY) {
        const top = [
            { x, y: height, z },
            { x: x + width, y: height, z },
            { x: x + width, y: height, z: z + depth },
            { x, y: height, z: z + depth },
        ].map((point) => project(point, scale, centerX, centerY));
        const front = [
            { x, y: 0, z: z + depth },
            { x: x + width, y: 0, z: z + depth },
            { x: x + width, y: height, z: z + depth },
            { x, y: height, z: z + depth },
        ].map((point) => project(point, scale, centerX, centerY));
        const right = [
            { x: x + width, y: 0, z },
            { x: x + width, y: 0, z: z + depth },
            { x: x + width, y: height, z: z + depth },
            { x: x + width, y: height, z },
        ].map((point) => project(point, scale, centerX, centerY));

        return { top, front, right };
    }

    function drawFloorplan() {
        const bounds = canvas.getBoundingClientRect();
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        const width = Math.max(bounds.width, 1);
        const height = Math.max(bounds.height, 1);

        if (canvas.width !== Math.round(width * pixelRatio) || canvas.height !== Math.round(height * pixelRatio)) {
            canvas.width = Math.round(width * pixelRatio);
            canvas.height = Math.round(height * pixelRatio);
        }

        context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        context.clearRect(0, 0, width, height);
        state.roomPolygons.clear();

        const centerX = width * (width < 560 ? 0.48 : 0.49);
        const centerY = height * 0.56;
        const scale = Math.min(width / 7.6, height / 5.2) * state.zoom;
        const plate = [
            { x: -2.75, y: 0, z: -2.05 }, { x: 2.75, y: 0, z: -2.05 },
            { x: 2.75, y: 0, z: 2.05 }, { x: -2.75, y: 0, z: 2.05 },
        ].map((point) => project(point, scale, centerX, centerY));
        const plateSide = [
            { x: -2.75, y: -0.19, z: 2.05 }, { x: 2.75, y: -0.19, z: 2.05 },
            { x: 2.75, y: 0, z: 2.05 }, { x: -2.75, y: 0, z: 2.05 },
        ].map((point) => project(point, scale, centerX, centerY));
        drawPolygon(plateSide, colorToken('--color-map-floor-side'));
        drawPolygon(plate, colorToken('--color-map-floor'), colorToken('--color-accent'), 1.2);

        const roomPositions = [
            { x: -2.42, z: -1.72 }, { x: -0.76, z: -1.72 }, { x: 0.90, z: -1.72 },
            { x: -1.58, z: 0.37 }, { x: 0.08, z: 0.37 },
        ];
        const floorLineA = project({ x: -2.55, y: 0.015, z: 0 }, scale, centerX, centerY);
        const floorLineB = project({ x: 2.55, y: 0.015, z: 0 }, scale, centerX, centerY);
        drawPolygon([floorLineA, floorLineB, { x: floorLineB.x, y: floorLineB.y + 1 }, { x: floorLineA.x, y: floorLineA.y + 1 }], 'transparent', colorToken('--color-accent'), 1);

        const depthOrder = rooms().map((room, index) => ({ room, position: roomPositions[index] }))
            .sort((first, second) => first.position.z - second.position.z);

        depthOrder.forEach(({ room, position }) => {
            const faces = boxFaces(position.x, position.z, 1.48, 1.42, 0.22, scale, centerX, centerY);
            const palette = statusColors[room.status];
            const isFiltered = state.filter !== 'all' && state.filter !== room.status;
            const opacity = isFiltered ? 0.2 : 1;
            const isSelected = state.selectedRoom === room.id;
            const faceStroke = isSelected ? colorToken('--color-primary') : colorToken('--color-map-canvas-outline');

            context.globalAlpha = opacity;
            drawPolygon(faces.front, palette.side, faceStroke, isSelected ? 2 : 0.8);
            drawPolygon(faces.right, colorToken('--color-map-wall'), faceStroke, isSelected ? 2 : 0.8);
            drawPolygon(faces.top, palette.fill, faceStroke, isSelected ? 2 : 0.8);
            state.roomPolygons.set(room.id, faces.top);

            const labelPoint = project({ x: position.x + 0.74, y: 0.235, z: position.z + 0.74 }, scale, centerX, centerY);
            context.fillStyle = palette.label;
            context.font = `700 ${Math.max(8, Math.min(12, scale * 0.16))}px 'Segoe UI', sans-serif`;
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(room.id, labelPoint.x, labelPoint.y - 1);
            context.globalAlpha = 1;
        });

        if (state.hoveredRoom && state.hoveredRoom !== state.selectedRoom) {
            const hoveredPolygon = state.roomPolygons.get(state.hoveredRoom);
            if (hoveredPolygon) {
                drawPolygon(hoveredPolygon, colorToken('--color-map-canvas-hover'), colorToken('--color-primary'), 2);
            }
        }
    }

    function pointInPolygon(point, polygon) {
        let inside = false;

        for (let current = 0, previous = polygon.length - 1; current < polygon.length; previous = current++) {
            const a = polygon[current];
            const b = polygon[previous];
            const crosses = (a.y > point.y) !== (b.y > point.y)
                && point.x < ((b.x - a.x) * (point.y - a.y)) / ((b.y - a.y) || 1) + a.x;

            if (crosses) {
                inside = !inside;
            }
        }

        return inside;
    }

    function roomAt(point) {
        const candidates = [...state.roomPolygons.entries()].reverse();
        const match = candidates.find(([, polygon]) => pointInPolygon(point, polygon));
        return match ? match[0] : null;
    }

    function roomById(roomId) {
        return rooms().find((room) => room.id === roomId);
    }

    function showTooltip(room, clientX, clientY) {
        if (!room || !tooltip) {
            tooltip?.setAttribute('hidden', '');
            return;
        }

        const stageRect = mapStage.getBoundingClientRect();
        const color = statusColors[room.status].fill;
        tooltip.innerHTML = `<span class="tooltip-room">ROOM ${room.id} · ${room.name}</span><span class="tooltip-status" style="color:${color}"><i></i>${statusColors[room.status].text}</span>`;
        tooltip.style.left = `${Math.max(10, Math.min(clientX - stageRect.left + 14, stageRect.width - 220))}px`;
        tooltip.style.top = `${Math.max(12, Math.min(clientY - stageRect.top - 16, stageRect.height - 65))}px`;
        tooltip.removeAttribute('hidden');
    }

    function setSelectedRoom(roomId) {
        const room = roomById(roomId);
        if (!room || !detailCard) {
            return;
        }

        state.selectedRoom = room.id;
        document.querySelector('#detail-room-id').textContent = `ROOM ${room.id}`;
        document.querySelector('#detail-room-name').textContent = room.name;
        document.querySelector('#detail-capacity').textContent = `${room.capacity} people`;
        document.querySelector('#detail-equipment').textContent = room.equipment;
        document.querySelector('#detail-next').textContent = room.next;
        document.querySelector('#detail-status').innerHTML = `<span class="status-badge ${room.status}">${statusColors[room.status].text}</span>`;
        detailCard.removeAttribute('hidden');

        document.querySelectorAll('.room-status-row').forEach((row) => {
            row.classList.toggle('is-selected', row.dataset.roomId === room.id);
        });

        drawFloorplan();
    }

    function renderRoomRows() {
        if (!roomList) {
            return;
        }

        const visibleRooms = rooms().filter((room) => state.filter === 'all' || room.status === state.filter);
        roomList.replaceChildren();

        visibleRooms.forEach((room) => {
            const row = document.createElement('button');
            row.className = `room-status-row${state.selectedRoom === room.id ? ' is-selected' : ''}`;
            row.type = 'button';
            row.dataset.roomId = room.id;

            const number = document.createElement('span');
            number.className = 'room-number';
            number.textContent = room.id;

            const nameGroup = document.createElement('span');
            nameGroup.className = 'room-name-group';
            const name = document.createElement('strong');
            name.textContent = room.name;
            const floor = document.createElement('small');
            floor.textContent = `${state.floor}${state.floor === 1 ? 'st' : 'nd'} floor · ${room.capacity} seats`;
            nameGroup.append(name, floor);

            const badge = document.createElement('span');
            badge.className = `status-badge ${room.status}`;
            badge.textContent = statusColors[room.status]?.text ?? 'Unknown';
            row.append(number, nameGroup, badge);
            roomList.append(row);
        });

        if (visibleRooms.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'room-empty-state';
            emptyState.textContent = 'No rooms match this status on the selected floor.';
            roomList.append(emptyState);
        }

        const countLabel = document.querySelector('.panel-count');
        if (countLabel) {
            countLabel.replaceChildren(document.createTextNode(String(visibleRooms.length)), document.createElement('small'));
            countLabel.querySelector('small').textContent = ' rooms';
        }

        if (state.selectedRoom && !visibleRooms.some((room) => room.id === state.selectedRoom)) {
            state.selectedRoom = null;
            detailCard?.setAttribute('hidden', '');
        }

        drawFloorplan();
    }

    async function loadFloorRooms(floor) {
        const currentRequestId = ++roomRequestId;
        const endpoint = roomList.dataset.endpoint;
        const loadMessage = document.querySelector('#room-load-message');
        const availabilityPanel = document.querySelector('.availability-panel');

        availabilityPanel.setAttribute('aria-busy', 'true');
        loadMessage.hidden = true;
        loadMessage.textContent = '';

        try {
            const response = await fetch(`${endpoint}?floor=${encodeURIComponent(floor)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Room data request failed with status ${response.status}.`);
            }

            const payload = await response.json();
            if (!Array.isArray(payload.data) || currentRequestId !== roomRequestId) {
                return;
            }

            floorRooms[Number(floor)] = payload.data;
            renderRoomRows();
        } catch (error) {
            if (currentRequestId !== roomRequestId) {
                return;
            }

            roomList.replaceChildren();
            loadMessage.textContent = 'Room status could not be loaded. Check your connection and try another floor.';
            loadMessage.hidden = false;
            console.error(error);
            drawFloorplan();
        } finally {
            if (currentRequestId === roomRequestId) {
                availabilityPanel.setAttribute('aria-busy', 'false');
            }
        }
    }

    function updateFloor(floor) {
        state.floor = Number(floor);
        state.selectedRoom = null;
        state.hoveredRoom = null;
        detailCard?.setAttribute('hidden', '');
        floorLabel.textContent = `LEVEL 0${state.floor}`;
        loadFloorRooms(state.floor);
    }

    function canvasPoint(event) {
        const bounds = canvas.getBoundingClientRect();
        return { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
    }

    canvas.addEventListener('pointerdown', (event) => {
        state.dragging = true;
        state.lastPointer = { x: event.clientX, y: event.clientY };
        canvas.classList.add('is-dragging');
        canvas.setPointerCapture(event.pointerId);
    });

    canvas.addEventListener('pointermove', (event) => {
        const pointer = canvasPoint(event);

        if (state.dragging && state.lastPointer) {
            const deltaX = event.clientX - state.lastPointer.x;
            const deltaY = event.clientY - state.lastPointer.y;
            state.yaw += deltaX * 0.008;
            state.pitch = Math.max(0.28, Math.min(1.12, state.pitch + deltaY * 0.006));
            state.lastPointer = { x: event.clientX, y: event.clientY };
            state.hoveredRoom = null;
            tooltip?.setAttribute('hidden', '');
            drawFloorplan();
            return;
        }

        state.pointer = { x: event.clientX, y: event.clientY };
        state.hoveredRoom = roomAt(pointer);
        if (state.hoveredRoom) {
            showTooltip(roomById(state.hoveredRoom), event.clientX, event.clientY);
        } else {
            tooltip?.setAttribute('hidden', '');
        }
        drawFloorplan();
    });

    function endDrag(event) {
        state.dragging = false;
        state.lastPointer = null;
        canvas.classList.remove('is-dragging');
        if (canvas.hasPointerCapture(event.pointerId)) {
            canvas.releasePointerCapture(event.pointerId);
        }
    }

    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);
    canvas.addEventListener('pointerleave', () => {
        if (!state.dragging) {
            state.hoveredRoom = null;
            tooltip?.setAttribute('hidden', '');
            drawFloorplan();
        }
    });

    canvas.addEventListener('click', (event) => {
        if (state.dragging) {
            return;
        }
        const roomId = roomAt(canvasPoint(event));
        if (roomId) {
            setSelectedRoom(roomId);
        }
    });

    canvas.addEventListener('wheel', (event) => {
        event.preventDefault();
        state.zoom = Math.max(0.66, Math.min(1.8, state.zoom + (event.deltaY < 0 ? 0.08 : -0.08)));
        drawFloorplan();
    }, { passive: false });

    document.querySelectorAll('[data-map-action]').forEach((button) => {
        button.addEventListener('click', async () => {
            const action = button.dataset.mapAction;
            if (action === 'zoom-in') {
                state.zoom = Math.min(1.8, state.zoom + 0.16);
            } else if (action === 'zoom-out') {
                state.zoom = Math.max(0.66, state.zoom - 0.16);
            } else if (action === 'center') {
                state.yaw = -0.55;
                state.pitch = 0.67;
                state.zoom = 1;
            } else if (action === 'rotate') {
                state.yaw += Math.PI / 6;
            } else if (action === 'fullscreen') {
                if (document.fullscreenElement === mapStage) {
                    await document.exitFullscreen();
                } else if (mapStage.requestFullscreen) {
                    await mapStage.requestFullscreen();
                }
            }
            drawFloorplan();
        });
    });

    statusSelect.addEventListener('change', () => {
        state.filter = statusSelect.value;
        renderRoomRows();
    });

    floorSelect.addEventListener('change', () => updateFloor(floorSelect.value));
    roomList.addEventListener('click', (event) => {
        const row = event.target.closest('[data-room-id]');
        if (row && !row.hidden) {
            setSelectedRoom(row.dataset.roomId);
        }
    });
    document.querySelector('#close-room-detail').addEventListener('click', () => {
        state.selectedRoom = null;
        detailCard.setAttribute('hidden', '');
        document.querySelectorAll('.room-status-row').forEach((row) => row.classList.remove('is-selected'));
        drawFloorplan();
    });

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
    document.querySelectorAll('.sidebar a[href^="#"]').forEach((link) => {
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

    const resizeObserver = new ResizeObserver(drawFloorplan);
    resizeObserver.observe(mapStage);
    drawFloorplan();
    loadFloorRooms(state.floor);
}
