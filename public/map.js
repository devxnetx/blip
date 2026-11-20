const CATEGORIES = (window.KOSTAA_CATEGORIES && Object.keys(window.KOSTAA_CATEGORIES).length)
  ? window.KOSTAA_CATEGORIES
  : {
      seeking: 'Търся',
      lost: 'Загубено',
      warning: 'Внимание',
      free: 'Безплатно',
      other: 'Друго',
    };

function categoryLabel(id) {
  return CATEGORIES[id] || '';
}
const REVEAL_DELTA = 0.012;
const CITIES = [
  { name: 'София', find: 'sofia sofiq', latitude: 42.6977, longitude: 23.3219 },
  { name: 'Пловдив', find: 'plovdiv', latitude: 42.1354, longitude: 24.7453 },
  { name: 'Варна', find: 'varna', latitude: 43.2141, longitude: 27.9147 },
  { name: 'Бургас', find: 'burgas', latitude: 42.5048, longitude: 27.4626 },
  { name: 'Стара Загора', find: 'stara zagora', latitude: 42.4258, longitude: 25.6345 },
  { name: 'Русе', find: 'ruse', latitude: 43.8356, longitude: 25.9657 },
  { name: 'Плевен', latitude: 43.417, longitude: 24.6067 },
  { name: 'Сливен', latitude: 42.6816, longitude: 26.3292 },
  { name: 'Добрич', latitude: 43.5726, longitude: 27.8273 },
  { name: 'Шумен', latitude: 43.2712, longitude: 26.9361 },
  { name: 'Перник', latitude: 42.6052, longitude: 23.0378 },
  { name: 'Хасково', latitude: 41.9344, longitude: 25.5554 },
  { name: 'Ямбол', latitude: 42.4842, longitude: 26.5035 },
  { name: 'Пазарджик', latitude: 42.1928, longitude: 24.3336 },
  { name: 'Благоевград', latitude: 42.0119, longitude: 23.09 },
  { name: 'Велико Търново', latitude: 43.0757, longitude: 25.6172 },
  { name: 'Враца', latitude: 43.2102, longitude: 23.5529 },
  { name: 'Габрово', latitude: 42.8742, longitude: 25.3187 },
  { name: 'Видин', latitude: 43.9916, longitude: 22.8824 },
  { name: 'Монтана', latitude: 43.4125, longitude: 23.225 },
  { name: 'Ловеч', latitude: 43.137, longitude: 24.7142 },
  { name: 'Кърджали', latitude: 41.6338, longitude: 25.3777 },
  { name: 'Кюстендил', latitude: 42.2839, longitude: 22.6911 },
  { name: 'Разград', latitude: 43.5332, longitude: 26.5242 },
  { name: 'Силистра', latitude: 44.1147, longitude: 27.2672 },
  { name: 'Търговище', latitude: 43.2494, longitude: 26.5727 },
  { name: 'Смолян', latitude: 41.5774, longitude: 24.7129 },
  { name: 'Казанлък', latitude: 42.6194, longitude: 25.393 },
];

const map = new maplibregl.Map({
  container: 'map',
  style: 'https://tiles.openfreemap.org/styles/liberty',
  center: [25.6345, 42.4258],
  zoom: 12.2,
  pitch: 48,
  bearing: -14,
  maxPitch: 75,
  minZoom: 3,
  maxZoom: 19,
  attributionControl: true,
});
map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'bottom-left');
let viewTouched = false;
let placed = false;
map.on('dragstart', () => {
  viewTouched = true;
});
map.getContainer().addEventListener('wheel', () => {
  viewTouched = true;
}, { passive: true });
map.on('zoomstart', (event) => {
  if (event.originalEvent) {
    viewTouched = true;
  }
});
map.on('rotatestart', (event) => {
  if (event.originalEvent) {
    viewTouched = true;
  }
});
map.on('pitchstart', (event) => {
  if (event.originalEvent) {
    viewTouched = true;
  }
});

const panel = document.getElementById('panel');
const nearbyEl = document.getElementById('nearby');
const banner = document.getElementById('banner');
const empty = document.getElementById('empty');
const replyText = document.getElementById('reply-text');

let pins = [];
let markers = [];
let selected = null;
let replies = [];
let replyError = '';
let listCenter = null;
let requestId = 0;
let nearbyRequest = 0;
let nearbyRows = [];
let listCategory = '';
let loadTimer = 0;
let picking = false;
let placingId = null;
let unassignedRows = [];
let draftMarker = null;

function deviceId() {
  const key = 'blip-device-id';
  let id = localStorage.getItem(key);
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem(key, id);
  }
  return id;
}

function headers() {
  return {
    Accept: 'application/json',
    'X-Device-Id': deviceId(),
  };
}

async function readError(response) {
  try {
    const body = await response.json();
    const errors = body.errors || {};
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) {
      return first[0];
    }
    return body.message || 'Нещо се обърка.';
  } catch {
    return 'Нещо се обърка.';
  }
}

function currentRegion() {
  const bounds = map.getBounds();
  const center = map.getCenter();
  return {
    latitude: center.lat,
    longitude: center.lng,
    latitudeDelta: bounds.getNorth() - bounds.getSouth(),
    longitudeDelta: bounds.getEast() - bounds.getWest(),
  };
}

function timeLeft(expiresAt, now = Date.now()) {
  const ms = new Date(expiresAt).getTime() - now;
  if (!Number.isFinite(ms) || ms <= 0) {
    return 'Изтече';
  }
  const minutes = Math.max(1, Math.ceil(ms / 60000));
  if (minutes < 60) {
    return `още ${minutes} мин`;
  }
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  return rest === 0 ? `още ${hours} ч` : `още ${hours} ч ${rest} мин`;
}

function formatWhen(iso) {
  const date = new Date(iso);
  if (!Number.isFinite(date.getTime())) {
    return '';
  }
  return new Intl.DateTimeFormat('bg-BG', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function upcomingLabel(pin) {
  if (!pin || pin.kind !== 'upcoming' || !pin.starts_at) {
    return '';
  }
  if (new Date(pin.starts_at).getTime() <= Date.now()) {
    return '';
  }
  const when = formatWhen(pin.starts_at);
  return when ? `Предстоящо · ${when}` : 'Предстоящо';
}

function durationText(minutes) {
  if (minutes === 5) {
    return '5 мин';
  }
  if (minutes === 60) {
    return '1 час';
  }
  if (minutes === 1440) {
    return '24 часа';
  }
  return '';
}

function timeAgo(createdAt, now = Date.now()) {
  const ms = now - new Date(createdAt).getTime();
  if (!Number.isFinite(ms) || ms < 45000) {
    return 'Току-що';
  }
  const minutes = Math.floor(ms / 60000);
  if (minutes < 60) {
    return minutes === 1 ? 'преди 1 мин' : `преди ${minutes} мин`;
  }
  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return hours === 1 ? 'преди 1 час' : `преди ${hours} часа`;
  }
  const days = Math.floor(hours / 24);
  return days === 1 ? 'преди 1 ден' : `преди ${days} дни`;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function safeUrl(url) {
  return typeof url === 'string' && /^https?:\/\//i.test(url) ? escapeHtml(url) : '';
}

function kmBetween(fromLat, fromLng, toLat, toLng) {
  const latDelta = (toLat - fromLat) * Math.PI / 180;
  const lngDelta = (toLng - fromLng) * Math.PI / 180;
  const from = fromLat * Math.PI / 180;
  const to = toLat * Math.PI / 180;
  const haversine = Math.sin(latDelta / 2) ** 2
    + Math.cos(from) * Math.cos(to) * Math.sin(lngDelta / 2) ** 2;
  return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(haversine)));
}

function formatDistance(kilometers) {
  if (kilometers < 1) {
    return `${Math.max(1, Math.round(kilometers * 1000))} м`;
  }
  if (kilometers < 10) {
    return `${Math.round(kilometers * 10) / 10} км`;
  }
  return `${Math.round(kilometers)} км`;
}

function boxForRadius(latitude, longitude, kilometers) {
  const latitudeDelta = (kilometers * 2) / 111.32;
  const cosine = Math.cos(latitude * Math.PI / 180);
  return {
    latitude,
    longitude,
    latitudeDelta,
    longitudeDelta: latitudeDelta / Math.max(0.2, Math.abs(cosine)),
  };
}

function sameSpot(a, b) {
  return Math.abs(a.latitude - b.latitude) < 0.00015
    && Math.abs(a.longitude - b.longitude) < 0.00015;
}

function groupPins(rows, region) {
  if (!rows.length || !region || !region.latitudeDelta) {
    return { clusters: [], singles: [] };
  }
  if (region.latitudeDelta <= REVEAL_DELTA) {
    const sorted = [...rows].sort((a, b) => a.id - b.id);
    const stacks = [];
    const singles = [];
    sorted.forEach((pin) => {
      let stack = stacks.find((item) => sameSpot(item, pin));
      if (!stack) {
        stack = { latitude: pin.latitude, longitude: pin.longitude, offset: 0 };
        stacks.push(stack);
      }
      singles.push({ ...pin, stackOffset: stack.offset });
      stack.offset += pin.image_url ? 132 : 80;
    });
    return { clusters: [], singles };
  }

  const latCell = region.latitudeDelta / 7;
  const lngCell = (region.longitudeDelta || region.latitudeDelta) / 7;
  const cells = new Map();
  rows.forEach((pin) => {
    const key = `${Math.floor(pin.latitude / latCell)}:${Math.floor(pin.longitude / lngCell)}`;
    const group = cells.get(key);
    if (group) {
      group.push(pin);
    } else {
      cells.set(key, [pin]);
    }
  });

  const clusters = [];
  const singles = [];
  cells.forEach((group, key) => {
    if (group.length === 1) {
      singles.push({ ...group[0], stackOffset: 0 });
      return;
    }
    const lats = group.map((pin) => pin.latitude);
    const lngs = group.map((pin) => pin.longitude);
    clusters.push({
      id: key,
      count: group.length,
      latitude: lats.reduce((sum, value) => sum + value, 0) / group.length,
      longitude: lngs.reduce((sum, value) => sum + value, 0) / group.length,
      latitudeSpan: Math.max(...lats) - Math.min(...lats),
      longitudeSpan: Math.max(...lngs) - Math.min(...lngs),
    });
  });
  return { clusters, singles };
}

function zoomRegion(region, cluster) {
  const span = Math.max(cluster.latitudeSpan || 0, cluster.longitudeSpan || 0);
  const latitudeDelta = Math.min(region.latitudeDelta * 0.45, Math.max(span * 1.8, 0.006));
  const ratio = region.longitudeDelta && region.latitudeDelta
    ? region.longitudeDelta / region.latitudeDelta
    : 1;
  return {
    latitude: cluster.latitude,
    longitude: cluster.longitude,
    latitudeDelta,
    longitudeDelta: latitudeDelta * ratio,
  };
}

function frameMap(west, south, east, north, duration, padding) {
  placed = true;
  map.fitBounds([[west, south], [east, north]], {
    pitch: Math.max(map.getPitch(), 48),
    bearing: map.getBearing(),
    maxZoom: 17,
    duration: duration ?? 700,
    padding: padding || 0,
  });
}

function showBounds(region) {
  frameMap(
    region.longitude - region.longitudeDelta / 2,
    region.latitude - region.latitudeDelta / 2,
    region.longitude + region.longitudeDelta / 2,
    region.latitude + region.latitudeDelta / 2,
  );
}

function markerElement(html, onClick) {
  const element = document.createElement('div');
  element.className = 'blip-icon';
  element.innerHTML = html;
  element.addEventListener('click', (event) => {
    event.stopPropagation();
    if (picking || placingId) {
      return;
    }
    onClick();
  });
  return element;
}

function drawMarkers() {
  markers.forEach((marker) => marker.remove());
  markers = [];
  const items = groupPins(pins, currentRegion());

  items.clusters.forEach((cluster) => {
    const width = Math.max(46, 16 + String(cluster.count).length * 12);
    const element = markerElement(
      `<div class="count" style="width:${width}px">(${cluster.count})</div>`,
      () => showBounds(zoomRegion(currentRegion(), cluster)),
    );
    const marker = new maplibregl.Marker({ element, anchor: 'center' })
      .setLngLat([cluster.longitude, cluster.latitude])
      .addTo(map);
    markers.push(marker);
  });

  items.singles.forEach((pin) => {
    const kind = categoryLabel(pin.category);
    const when = upcomingLabel(pin);
    const base = (pin.image_url ? 132 : 80) + (kind ? 18 : 0) + (when ? 36 : 0);
    const image = safeUrl(pin.image_url)
      ? `<img src="${safeUrl(pin.image_url)}" alt="">`
      : '';
    const kindHtml = kind ? `<small>${escapeHtml(kind)}</small>` : '';
    const whenHtml = when ? `<small>${escapeHtml(when)}</small>` : '';
    const element = markerElement(
      `<div class="bubble-wrap" style="height:${base}px;padding-bottom:${pin.stackOffset || 0}px"><div class="bubble">${image}<span>${escapeHtml(pin.message)}</span>${kindHtml}${whenHtml}</div><div class="tail"></div></div>`,
      () => {
        focusPin(pin, { padPanel: true });
        openPin(pin);
      },
    );
    const marker = new maplibregl.Marker({ element, anchor: 'bottom' })
      .setLngLat([pin.longitude, pin.latitude])
      .addTo(map);
    markers.push(marker);
  });
}

async function loadPins() {
  if (!placed) {
    return;
  }
  const id = ++requestId;
  const bounds = map.getBounds();
  const params = new URLSearchParams({
    north: String(bounds.getNorth()),
    south: String(bounds.getSouth()),
    east: String(bounds.getEast()),
    west: String(bounds.getWest()),
  });

  try {
    const response = await fetch(`/api/pins?${params}`, { headers: headers() });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (id !== requestId) {
      return;
    }
    const now = Date.now();
    pins = (body.pins || []).filter((pin) => (
      pin.latitude != null
      && pin.longitude != null
      && pin.kind !== 'unassigned'
      && new Date(pin.expires_at).getTime() > now
    ));
    banner.hidden = true;
    empty.hidden = pins.length !== 0;
    if (selected) {
      const fresh = pins.find((pin) => pin.id === selected.id);
      const inside = bounds.contains([selected.latitude, selected.longitude]);
      if (fresh) {
        selected = { ...selected, ...fresh };
        renderPanel();
      } else if (inside) {
        closePanel();
      }
    }
    drawMarkers();
  } catch {
    if (id === requestId) {
      banner.hidden = false;
      empty.hidden = true;
    }
  }
}

function scheduleLoad() {
  clearTimeout(loadTimer);
  loadTimer = setTimeout(loadPins, 400);
}

function renderPanel() {
  if (!selected) {
    return;
  }
  document.getElementById('panel-message').textContent = selected.message;
  const category = document.getElementById('panel-category');
  const label = categoryLabel(selected.category);
  category.hidden = !label;
  category.textContent = label;
  document.getElementById('panel-time').textContent = upcomingLabel(selected) || timeLeft(selected.expires_at);
  const photo = document.getElementById('panel-photo');
  photo.hidden = !safeUrl(selected.image_url);
  photo.src = photo.hidden ? '' : selected.image_url;
  const story = document.getElementById('panel-description');
  story.hidden = !selected.description;
  story.textContent = selected.description || '';

  const replyBox = document.getElementById('panel-replies');
  replyBox.replaceChildren();
  const error = document.getElementById('panel-reply-error');
  error.hidden = !replyError;
  error.textContent = replyError;

  replies.forEach((reply) => {
    const block = document.createElement('div');
    block.className = reply.mine ? 'reply mine' : 'reply';
    const message = document.createElement('p');
    message.textContent = reply.message;
    const when = document.createElement('time');
    when.textContent = timeAgo(reply.created_at);
    block.append(message, when);
    if (reply.mine || reply.author_only) {
      const only = document.createElement('span');
      only.className = 'only';
      only.textContent = reply.mine
        ? (reply.author_only ? 'Ти · само за автора' : 'Ти')
        : 'Само за теб';
      block.append(only);
    }
    replyBox.append(block);
  });

  const imported = Boolean(selected.imported);
  document.getElementById('panel-sent').hidden = imported || selected.mine || !selected.replied;
  document.getElementById('reply-form').hidden = imported || selected.mine || selected.replied;
  document.getElementById('report').hidden = imported || selected.mine || selected.reported;
  document.getElementById('reported').hidden = imported || selected.mine || !selected.reported;
  document.getElementById('remove').hidden = !selected.mine || imported;
}

async function openPin(pin) {
  selected = pin;
  replies = [];
  replyError = '';
  replyText.value = '';
  document.getElementById('reply-private').checked = false;
  document.getElementById('reply-count').textContent = '0/200';
  document.getElementById('reply-form-error').hidden = true;
  document.getElementById('copy-status').hidden = true;
  nearbyEl.hidden = true;
  panel.hidden = false;
  renderPanel();

  if (pin.imported) {
    return;
  }

  try {
    const response = await fetch(`/api/pins/${pin.id}/replies`, { headers: headers() });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (!selected || selected.id !== pin.id) {
      return;
    }
    replies = body.replies || [];
    renderPanel();
  } catch {
    if (selected && selected.id === pin.id) {
      replyError = 'Отговорите не се заредиха.';
      renderPanel();
    }
  }
}

function closePanel() {
  const wasOpen = !panel.hidden;
  selected = null;
  replies = [];
  panel.hidden = true;
  if (wasOpen) {
    restoreView();
  }
}

function dropPinFromMap(id) {
  pins = pins.filter((pin) => pin.id !== id);
  empty.hidden = pins.length !== 0;
  closePanel();
  drawMarkers();
}

function pinUrl(pin) {
  const url = new URL('/map', window.location.origin);
  url.searchParams.set('pin', String(pin.id));
  return url.toString();
}

async function copyText(value) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return true;
    }
  } catch {
    // The textarea fallback covers a page that is not a secure context.
  }
  try {
    const area = document.createElement('textarea');
    area.value = value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    document.body.append(area);
    area.select();
    const copied = document.execCommand('copy');
    area.remove();
    return copied;
  } catch {
    return false;
  }
}

async function copyPinLink(pin, button) {
  const url = pinUrl(pin);
  const copied = await copyText(url);
  if (button) {
    const label = button.dataset.label;
    button.textContent = copied ? 'Копирано' : 'Не се копира';
    setTimeout(() => {
      button.textContent = label;
    }, 1600);
    return;
  }
  const status = document.getElementById('copy-status');
  status.hidden = false;
  status.textContent = copied ? 'Връзката е копирана.' : url;
}

async function sharePin(pin, button) {
  const url = pinUrl(pin);
  if (navigator.share) {
    try {
      await navigator.share({ title: pin.message, text: pin.message, url });
      return;
    } catch (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
    }
  }
  await copyPinLink(pin, button);
}

document.getElementById('panel-close').addEventListener('click', closePanel);
document.getElementById('copy-link').addEventListener('click', () => {
  if (selected) {
    copyPinLink(selected);
  }
});
document.getElementById('share-link').addEventListener('click', () => {
  if (selected) {
    sharePin(selected);
  }
});

replyText.addEventListener('input', () => {
  document.getElementById('reply-count').textContent = `${replyText.value.trim().length}/200`;
});

document.getElementById('reply-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const text = replyText.value.trim();
  const error = document.getElementById('reply-form-error');
  if (!text || !selected) {
    return;
  }
  const send = document.getElementById('reply-send');
  send.disabled = true;
  try {
    const response = await fetch(`/api/pins/${selected.id}/replies`, {
      method: 'POST',
      headers: { ...headers(), 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: text,
        author_only: document.getElementById('reply-private').checked,
      }),
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const created = await response.json();
    selected = { ...selected, replied: true };
    replies = [...replies.filter((item) => item.id !== created.id), { ...created, mine: true }];
    replyText.value = '';
    document.getElementById('reply-private').checked = false;
    error.hidden = true;
    renderPanel();
  } catch (err) {
    error.hidden = false;
    error.textContent = err.message || 'Отговорът не е изпратен.';
  } finally {
    send.disabled = false;
  }
});

document.getElementById('report').addEventListener('click', async () => {
  if (!selected || !window.confirm('Докладваш ли този пин? Пет доклада от различни хора го махат.')) {
    return;
  }
  try {
    const response = await fetch(`/api/pins/${selected.id}/report`, {
      method: 'POST',
      headers: headers(),
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (body.deleted) {
      dropPinFromMap(selected.id);
      return;
    }
    selected = { ...selected, reported: true };
    renderPanel();
  } catch (err) {
    window.alert(err.message || 'Пинът не можа да бъде докладван.');
  }
});

document.getElementById('remove').addEventListener('click', async () => {
  if (!selected || !window.confirm('Махаш ли този пин?')) {
    return;
  }
  try {
    const response = await fetch(`/api/pins/${selected.id}`, {
      method: 'DELETE',
      headers: headers(),
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    dropPinFromMap(selected.id);
  } catch (err) {
    window.alert(err.message || 'Пинът не можа да бъде махнат.');
  }
});

async function loadNearby(kilometers) {
  if (!listCenter) {
    return;
  }
  const id = ++nearbyRequest;
  const status = document.getElementById('nearby-status');
  const list = document.getElementById('nearby-list');
  status.textContent = 'Зарежда се...';
  const region = boxForRadius(listCenter.lat, listCenter.lng, kilometers);
  const params = new URLSearchParams({
    north: String(region.latitude + region.latitudeDelta / 2),
    south: String(region.latitude - region.latitudeDelta / 2),
    east: String(region.longitude + region.longitudeDelta / 2),
    west: String(region.longitude - region.longitudeDelta / 2),
  });

  try {
    const response = await fetch(`/api/pins?${params}`, { headers: headers() });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (id !== nearbyRequest) {
      return;
    }
    nearbyRows = (body.pins || [])
      .map((pin) => ({
        ...pin,
        km: kmBetween(listCenter.lat, listCenter.lng, pin.latitude, pin.longitude),
      }))
      .filter((pin) => pin.km <= kilometers + 0.05)
      .sort((a, b) => a.km - b.km);
    renderNearbyList();
  } catch (err) {
    if (id === nearbyRequest) {
      nearbyRows = [];
      list.replaceChildren();
      status.textContent = err.message || 'Списъкът не се зареди.';
    }
  }
}

function renderNearbyList() {
  const status = document.getElementById('nearby-status');
  const list = document.getElementById('nearby-list');
  const kilometers = Number(document.getElementById('radius').value);
  const rows = nearbyRows.filter((pin) => !listCategory || pin.category === listCategory);
  list.replaceChildren();
  rows.forEach((pin) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pin-open';
      if (safeUrl(pin.image_url)) {
        const image = document.createElement('img');
        image.src = pin.image_url;
        image.alt = '';
        button.append(image);
      }
      const copy = document.createElement('span');
      const message = document.createElement('strong');
      message.textContent = pin.message;
      copy.append(message);
      const label = categoryLabel(pin.category);
      if (label) {
        const kind = document.createElement('span');
        kind.className = 'pin-category';
        kind.textContent = label;
        copy.append(kind);
      }
      const meta = document.createElement('small');
      meta.textContent = `${formatDistance(pin.km)} · ${upcomingLabel(pin) || timeLeft(pin.expires_at)}`;
      copy.append(meta);
      button.append(copy);
      button.addEventListener('click', () => {
        nearbyEl.hidden = true;
        if (focusPin(pin)) {
          openPin(pin);
          return;
        }
        map.panTo([pin.longitude, pin.latitude]);
      });
      const actions = document.createElement('div');
      actions.className = 'pin-actions';
      const copyLink = document.createElement('button');
      copyLink.type = 'button';
      copyLink.dataset.label = 'Копирай';
      copyLink.textContent = 'Копирай';
      copyLink.addEventListener('click', () => copyPinLink(pin, copyLink));
      const shareLink = document.createElement('button');
      shareLink.type = 'button';
      shareLink.dataset.label = 'Сподели';
      shareLink.textContent = 'Сподели';
      shareLink.addEventListener('click', () => sharePin(pin, shareLink));
      actions.append(copyLink, shareLink);
      item.append(button, actions);
      list.append(item);
    });
  if (!nearbyRows.length) {
    status.textContent = `Няма нищо в радиус от ${kilometers} км.`;
  } else if (!rows.length) {
    status.textContent = 'Няма нищо в тази категория.';
  } else {
    status.textContent = '';
  }
}

document.getElementById('category-filters').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  if (!button || !document.getElementById('category-filters').contains(button)) {
    return;
  }
  listCategory = button.dataset.category || '';
  document.querySelectorAll('#category-filters button').forEach((item) => {
    item.classList.toggle('on', item === button);
  });
  renderNearbyList();
});

document.getElementById('open-pins').addEventListener('click', () => {
  listCenter = map.getCenter();
  closePanel();
  closeUnassigned();
  document.getElementById('cities-modal').hidden = true;
  nearbyEl.hidden = false;
  loadNearby(Number(document.getElementById('radius').value));
});

document.getElementById('nearby-close').addEventListener('click', () => {
  nearbyEl.hidden = true;
});

document.getElementById('radius').addEventListener('input', (event) => {
  document.getElementById('radius-label').innerHTML = `<b>${event.target.value} км</b>`;
});

document.getElementById('radius').addEventListener('change', (event) => {
  loadNearby(Number(event.target.value));
});

document.getElementById('locate').addEventListener('click', () => {
  if (!navigator.geolocation) {
    window.alert('Местоположението не е достъпно в този браузър.');
    return;
  }
  navigator.geolocation.getCurrentPosition(
    (position) => {
      forgetView();
      map.panTo([position.coords.longitude, position.coords.latitude]);
    },
    () => window.alert('Kostaa не успя да прочете къде си.'),
  );
});

banner.addEventListener('click', loadPins);

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closePanel();
    nearbyEl.hidden = true;
    closeUnassigned();
    document.getElementById('cities-modal').hidden = true;
  }
});

const FEATURED = ['София', 'Пловдив', 'Варна', 'Бургас', 'Стара Загора'];

function cityButton(city, className) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = className;
  button.textContent = city.name;
  button.addEventListener('click', () => goToCity(city));
  return button;
}

function renderCityChoices() {
  const list = document.getElementById('city-list');
  const needle = document.getElementById('city-search').value.trim().toLowerCase();
  const rows = CITIES.filter((city) => {
    if (!needle) {
      return true;
    }
    return city.name.toLowerCase().includes(needle) || (city.find || '').includes(needle);
  });
  list.replaceChildren(...rows.map((city) => cityButton(city, 'city-choice')));
  document.getElementById('city-empty').hidden = rows.length > 0;
}

const moreCities = document.getElementById('open-cities');
FEATURED.forEach((name) => {
  const city = CITIES.find((item) => item.name === name);
  moreCities.before(cityButton(city, 'city-pill'));
});

function openCities() {
  closePanel();
  nearbyEl.hidden = true;
  closeUnassigned();
  document.getElementById('city-search').value = '';
  renderCityChoices();
  document.getElementById('cities-modal').hidden = false;
  document.getElementById('city-search').focus();
}

document.getElementById('open-cities').addEventListener('click', openCities);
document.getElementById('open-cities-mobile').addEventListener('click', openCities);

document.getElementById('city-search').addEventListener('input', renderCityChoices);

document.getElementById('cities-close').addEventListener('click', () => {
  document.getElementById('cities-modal').hidden = true;
});

const installEl = document.getElementById('install');
const installGo = document.getElementById('install-go');
const installHint = document.getElementById('install-hint');
const phoneScreen = window.matchMedia('(max-width: 800px)').matches;
const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
const ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
let installEvent = null;

function showInstall() {
  if (!phoneScreen || installed || localStorage.getItem('kostaa-install-dismissed') === '1') {
    return;
  }
  installHint.hidden = !ios;
  installGo.hidden = ios || !installEvent;
  installEl.hidden = false;
}

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  installEvent = event;
  showInstall();
});

installGo.addEventListener('click', async () => {
  if (!installEvent) {
    return;
  }
  installEvent.prompt();
  installEvent = null;
  installEl.hidden = true;
});

document.getElementById('install-close').addEventListener('click', () => {
  localStorage.setItem('kostaa-install-dismissed', '1');
  installEl.hidden = true;
});

if (phoneScreen && ios) {
  showInstall();
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}

map.on('moveend', () => {
  drawMarkers();
  scheduleLoad();
});

setInterval(() => {
  const now = Date.now();
  pins = pins.filter((pin) => new Date(pin.expires_at).getTime() > now);
  if (selected && new Date(selected.expires_at).getTime() <= now) {
    closePanel();
  }
  empty.hidden = pins.length !== 0 || !banner.hidden;
  drawMarkers();
  if (selected) {
    renderPanel();
  }
}, 30000);

const NEAR_KM = 2;
const FRESH_MS = 45000;
const toast = document.getElementById('toast');
let watchArmed = false;
let seenIds = new Set();
let toastPin = null;
let toastTimer = 0;
let geoState = 'idle';
let noteAsked = false;

function showToast(body, pin) {
  toastPin = pin;
  toast.replaceChildren();
  const title = document.createElement('b');
  title.textContent = 'Нов пин наблизо';
  const text = document.createElement('span');
  text.textContent = body;
  toast.append(title, text);
  toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.hidden = true;
  }, 8000);
  systemNote(body, pin);
}

async function systemNote(body, pin) {
  if (!('Notification' in window)) {
    return;
  }
  if (!noteAsked && Notification.permission === 'default') {
    noteAsked = true;
    try {
      await Notification.requestPermission();
    } catch {
      return;
    }
  }
  if (Notification.permission !== 'granted') {
    return;
  }
  const note = new Notification('Нов пин наблизо', { body });
  note.onclick = () => {
    window.focus();
    map.panTo([pin.longitude, pin.latitude]);
    note.close();
  };
}

toast.addEventListener('click', () => {
  if (toastPin) {
    map.panTo([toastPin.longitude, toastPin.latitude]);
  }
  toast.hidden = true;
});

async function scanNear(point) {
  const region = boxForRadius(point.lat, point.lng, NEAR_KM);
  const params = new URLSearchParams({
    north: String(region.latitude + region.latitudeDelta / 2),
    south: String(region.latitude - region.latitudeDelta / 2),
    east: String(region.longitude + region.longitudeDelta / 2),
    west: String(region.longitude - region.longitudeDelta / 2),
  });
  const response = await fetch(`/api/pins?${params}`, { headers: headers() });
  if (!response.ok) {
    return;
  }
  const body = await response.json();
  const rows = (body.pins || []).filter((pin) => (
    kmBetween(point.lat, point.lng, pin.latitude, pin.longitude) <= NEAR_KM + 0.05
  ));
  if (!watchArmed) {
    rows.forEach((pin) => seenIds.add(pin.id));
    watchArmed = true;
    return;
  }
  const now = Date.now();
  const fresh = rows.filter((pin) => {
    if (pin.mine || seenIds.has(pin.id)) {
      return false;
    }
    const created = new Date(pin.created_at).getTime();
    return Number.isFinite(created) && now - created <= FRESH_MS;
  }).sort((a, b) => (
    kmBetween(point.lat, point.lng, a.latitude, a.longitude)
    - kmBetween(point.lat, point.lng, b.latitude, b.longitude)
  ));
  fresh.forEach((pin) => seenIds.add(pin.id));
  if (!fresh.length) {
    return;
  }
  const extra = fresh.length - 1;
  const text = extra > 0 ? `${fresh[0].message} и още ${extra}` : fresh[0].message;
  showToast(text, fresh[0]);
}

function watchNearby() {
  if (!navigator.geolocation || geoState === 'denied' || geoState === 'asking') {
    return;
  }
  geoState = 'asking';
  navigator.geolocation.getCurrentPosition(
    (position) => {
      geoState = 'granted';
      scanNear({ lat: position.coords.latitude, lng: position.coords.longitude }).catch(() => {});
    },
    (error) => {
      geoState = error && error.code === 1 ? 'denied' : 'idle';
    },
    { enableHighAccuracy: false, maximumAge: 20000, timeout: 8000 },
  );
}

const desktopBoot = window.matchMedia('(min-width: 801px)').matches;
let introPlayed = false;
let introToken = 0;

function hideBoot() {
  const boot = document.getElementById('boot');
  if (!boot || boot.hidden || boot.classList.contains('is-done')) {
    return;
  }
  boot.classList.add('is-done');
  window.setTimeout(() => {
    boot.hidden = true;
  }, 450);
}

function showCity(latitude, longitude, options = {}) {
  const span = options.span || 0.08;
  const frame = (size, duration) => frameMap(
    longitude - size / 2,
    latitude - size / 2,
    longitude + size / 2,
    latitude + size / 2,
    duration,
  );
  if (desktopBoot && !introPlayed) {
    introPlayed = true;
    const token = ++introToken;
    const zoomIn = () => {
      if (token !== introToken) {
        return;
      }
      introToken += 1;
      hideBoot();
      frame(span, 1100);
    };
    const arm = () => {
      frame(Math.min(0.13, span * 1.8), 0);
      map.once('moveend', zoomIn);
      window.setTimeout(zoomIn, 700);
    };
    if (map.isStyleLoaded()) {
      arm();
    } else {
      map.once('load', arm);
    }
    return;
  }
  introToken += 1;
  hideBoot();
  frame(span, 900);
}

let viewBeforePin = null;

function rememberView() {
  if (!desktopBoot || viewBeforePin) {
    return;
  }
  const center = map.getCenter();
  viewBeforePin = {
    center: [center.lng, center.lat],
    zoom: map.getZoom(),
    pitch: map.getPitch(),
    bearing: map.getBearing(),
    viewTouched,
  };
}

function forgetView() {
  viewBeforePin = null;
}

function restoreView() {
  if (!viewBeforePin) {
    return;
  }
  const previous = viewBeforePin;
  viewBeforePin = null;
  viewTouched = previous.viewTouched;
  map.setPadding({ top: 0, right: 0, bottom: 0, left: 0 });
  map.easeTo({
    center: previous.center,
    zoom: previous.zoom,
    pitch: previous.pitch,
    bearing: previous.bearing,
    duration: 700,
  });
}

function focusPin(pin, options = {}) {
  if (!desktopBoot) {
    return false;
  }
  const latitude = Number(pin.latitude);
  const longitude = Number(pin.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
    return false;
  }
  rememberView();
  viewTouched = true;
  introToken += 1;
  const span = 0.006;
  const bounds = map.getBounds();
  const padding = options.padPanel
    ? { top: 48, right: 440, bottom: 48, left: 48 }
    : { top: 48, right: 48, bottom: 48, left: 48 };
  if (bounds.getNorth() - bounds.getSouth() <= span) {
    map.easeTo({
      center: [longitude, latitude],
      duration: 700,
      padding,
    });
    return true;
  }
  frameMap(
    longitude - span / 2,
    latitude - span / 2,
    longitude + span / 2,
    latitude + span / 2,
    850,
    padding,
  );
  return true;
}

function goToCity(city) {
  viewTouched = true;
  forgetView();
  document.getElementById('cities-modal').hidden = true;
  nearbyEl.hidden = true;
  closePanel();
  closeUnassigned();
  showCity(city.latitude, city.longitude);
}

let holdLink = /^\d+$/.test(new URLSearchParams(window.location.search).get('pin') || '');

function revealIfFar(point) {
  if (holdLink || !point) {
    return;
  }
  if (viewTouched && placed) {
    return;
  }
  if (placed) {
    const center = map.getCenter();
    if (kmBetween(center.lat, center.lng, point.lat, point.lng) <= 1) {
      return;
    }
  }
  showCity(point.lat, point.lng);
}

function cityPoint(latitude, longitude) {
  const lat = Number(latitude);
  const lng = Number(longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) {
    return null;
  }
  return { lat, lng };
}

async function lookupIpCity(url, read) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 2500);
  try {
    const response = await fetch(url, { signal: controller.signal });
    if (!response.ok) {
      return null;
    }
    return read(await response.json());
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

function followCity() {
  return new Promise((resolve) => {
    if (!navigator.geolocation || geoState === 'denied') {
      resolve(null);
      return;
    }
    geoState = 'asking';
    let settled = false;
    const watchId = navigator.geolocation.watchPosition(
      (position) => {
        geoState = 'granted';
        const point = cityPoint(position.coords.latitude, position.coords.longitude);
        if (!point) {
          return;
        }
        revealIfFar(point);
        if (!settled) {
          settled = true;
          resolve(point);
          scanNear(point).catch(() => {});
        }
        const accuracy = Number(position.coords.accuracy);
        if (Number.isFinite(accuracy) && accuracy > 0 && accuracy <= 300) {
          navigator.geolocation.clearWatch(watchId);
        }
      },
      (error) => {
        geoState = error && error.code === 1 ? 'denied' : 'idle';
        navigator.geolocation.clearWatch(watchId);
        if (!settled) {
          settled = true;
          resolve(null);
        }
      },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    );
    setTimeout(() => navigator.geolocation.clearWatch(watchId), 25000);
  });
}

async function openLinkedPin() {
  const id = new URLSearchParams(window.location.search).get('pin');
  if (!id || !/^\d+$/.test(id)) {
    return false;
  }
  try {
    const response = await fetch(`/api/pins/${id}`, { headers: headers() });
    if (!response.ok) {
      return false;
    }
    const body = await response.json();
    const pin = body.pin;
    if (!pin || !Number.isFinite(Number(pin.latitude)) || !Number.isFinite(Number(pin.longitude))) {
      return false;
    }
    pins = [pin, ...pins.filter((item) => item.id !== pin.id)];
    const span = 0.01;
    frameMap(
      Number(pin.longitude) - span / 2,
      Number(pin.latitude) - span / 2,
      Number(pin.longitude) + span / 2,
      Number(pin.latitude) + span / 2,
      0,
    );
    openPin(pin);
    hideBoot();
    return true;
  } catch {
    return false;
  }
}

async function openOnCity() {
  if (holdLink) {
    const opened = await openLinkedPin();
    if (opened) {
      return;
    }
    holdLink = false;
  }

  const staraZagora = CITIES.find((city) => city.name === 'Стара Загора');
  showCity(staraZagora.latitude, staraZagora.longitude, { span: 0.022 });
}

setInterval(() => loadPins(), 20000);
setInterval(watchNearby, 20000);
openOnCity();
watchNearby();

function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

function adminHeaders() {
  return {
    ...headers(),
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrfToken(),
  };
}

const banButton = document.getElementById('ban-device');
if (banButton) {
  banButton.addEventListener('click', async () => {
    if (!selected || !window.confirm('Забраняваш ли това устройство? Активните му пинове изчезват от картата и то не може да пуска нови.')) {
      return;
    }
    try {
      const response = await fetch(`/admin/pins/${selected.id}/ban`, {
        method: 'POST',
        headers: adminHeaders(),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const body = await response.json();
      const hidden = new Set(body.hidden_pin_ids || []);
      pins = pins.filter((pin) => !hidden.has(pin.id));
      empty.hidden = pins.length !== 0;
      closePanel();
      drawMarkers();
    } catch (err) {
      window.alert(err.message || 'Устройството не можа да бъде забранено.');
    }
  });
}

function stopPicking() {
  picking = false;
  map.getCanvas().style.cursor = '';
  const button = document.getElementById('pick-spot');
  if (button) {
    button.textContent = 'Избери на картата';
  }
}

function closeUnassigned() {
  placingId = null;
  const box = document.getElementById('unassigned');
  if (box) {
    box.hidden = true;
  }
  if (!picking) {
    map.getCanvas().style.cursor = '';
  }
}

function chooseFilter(box, input, key) {
  box.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button || !button.dataset[key]) {
      return;
    }
    input.value = button.dataset[key];
    box.querySelectorAll('button').forEach((item) => {
      item.classList.toggle('on', item === button);
    });
  });
}

const composer = document.getElementById('composer');
const openComposer = document.getElementById('open-composer');
if (composer && openComposer) {
  const pinMessage = document.getElementById('pin-message');
  const pinError = document.getElementById('pin-error');

  openComposer.addEventListener('click', () => {
    panel.hidden = true;
    nearbyEl.hidden = true;
    const cities = document.getElementById('cities-modal');
    if (cities) {
      cities.hidden = true;
    }
    composer.hidden = false;
    closeUnassigned();
  });

  document.getElementById('composer-close').addEventListener('click', () => {
    composer.hidden = true;
    stopPicking();
  });

  pinMessage.addEventListener('input', () => {
    document.getElementById('pin-count').textContent = `${pinMessage.value.trim().length}/80`;
  });

  chooseFilter(
    document.getElementById('composer-categories'),
    document.getElementById('pin-category'),
    'category',
  );
  chooseFilter(
    document.getElementById('composer-durations'),
    document.getElementById('pin-duration'),
    'duration',
  );
  chooseFilter(
    document.getElementById('composer-when'),
    document.getElementById('pin-kind'),
    'when',
  );
  document.getElementById('composer-when').addEventListener('click', () => {
    const upcoming = document.getElementById('pin-kind').value === 'upcoming';
    document.getElementById('pin-range').hidden = !upcoming;
    document.getElementById('composer-durations').hidden = upcoming;
  });

  document.getElementById('pick-spot').addEventListener('click', () => {
    placingId = null;
    picking = true;
    map.getCanvas().style.cursor = 'crosshair';
    document.getElementById('pick-spot').textContent = 'Кликни върху картата';
  });

  map.on('click', (event) => {
    if (!picking) {
      return;
    }
    const latitude = event.lngLat.lat;
    const longitude = event.lngLat.lng;
    document.getElementById('pin-lat').value = latitude.toFixed(6);
    document.getElementById('pin-lng').value = longitude.toFixed(6);
    document.getElementById('pin-spot').textContent = `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
    if (draftMarker) {
      draftMarker.remove();
    }
    draftMarker = new maplibregl.Marker({ color: '#c2410c' })
      .setLngLat([longitude, latitude])
      .addTo(map);
    stopPicking();
  });

  document.getElementById('admin-pin-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = pinMessage.value.trim();
    const latitude = document.getElementById('pin-lat').value;
    const longitude = document.getElementById('pin-lng').value;
    const kind = document.getElementById('pin-kind').value || 'normal';
    const startsAt = document.getElementById('pin-start').value;
    const endsAt = document.getElementById('pin-end').value;
    if (!message) {
      pinError.hidden = false;
      pinError.textContent = 'Напиши кратко съобщение.';
      return;
    }
    if (kind === 'upcoming' && (!startsAt || !endsAt)) {
      pinError.hidden = false;
      pinError.textContent = 'Избери от кога до кога.';
      return;
    }
    if (!latitude || !longitude) {
      pinError.hidden = false;
      pinError.textContent = 'Избери място на картата.';
      return;
    }
    const send = document.getElementById('pin-send');
    send.disabled = true;
    try {
      const payload = {
        message,
        latitude: Number(latitude),
        longitude: Number(longitude),
        category: document.getElementById('pin-category').value,
        kind,
      };
      if (kind === 'upcoming') {
        payload.starts_at = startsAt;
        payload.ends_at = endsAt;
      } else {
        payload.duration = document.getElementById('pin-duration').value;
      }
      const response = await fetch('/admin/pins', {
        method: 'POST',
        headers: { ...adminHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const created = await response.json();
      pins = [created, ...pins.filter((item) => item.id !== created.id)];
      empty.hidden = true;
      drawMarkers();
      if (draftMarker) {
        draftMarker.remove();
        draftMarker = null;
      }
      pinMessage.value = '';
      document.getElementById('pin-count').textContent = '0/80';
      document.getElementById('pin-lat').value = '';
      document.getElementById('pin-lng').value = '';
      document.getElementById('pin-start').value = '';
      document.getElementById('pin-end').value = '';
      document.getElementById('pin-spot').textContent = 'Няма избрано място.';
      pinError.hidden = true;
      composer.hidden = true;
      stopPicking();
    } catch (err) {
      pinError.hidden = false;
      pinError.textContent = err.message || 'Пинът не е пуснат.';
    } finally {
      send.disabled = false;
    }
  });
}

const unassignedEl = document.getElementById('unassigned');
const openUnassigned = document.getElementById('open-unassigned');
if (unassignedEl && openUnassigned) {
  const unassignedError = document.getElementById('unassigned-error');
  let placingLock = false;

  function renderUnassigned() {
    const list = document.getElementById('unassigned-list');
    const hint = document.getElementById('unassigned-hint');
    list.replaceChildren();
    hint.textContent = placingId
      ? 'Кликни върху картата, за да поставиш пина.'
      : 'Избери събитие, после кликни върху картата.';
    if (!unassignedRows.length) {
      const emptyRow = document.createElement('li');
      emptyRow.className = 'faint';
      emptyRow.textContent = 'Няма събития без място.';
      list.append(emptyRow);
      return;
    }
    unassignedRows.forEach((pin) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      if (placingId === pin.id) {
        button.className = 'on';
      }
      const message = document.createElement('strong');
      message.textContent = pin.message;
      const meta = document.createElement('small');
      meta.textContent = [
        categoryLabel(pin.category),
        pin.starts_at ? formatWhen(pin.starts_at) : '',
        durationText(pin.duration_minutes),
      ].filter(Boolean).join(' · ');
      button.append(message, meta);
      button.addEventListener('click', () => {
        placingId = pin.id;
        stopPicking();
        map.getCanvas().style.cursor = 'crosshair';
        renderUnassigned();
      });
      item.append(button);
      list.append(item);
    });
  }

  async function loadUnassigned() {
    const response = await fetch('/admin/pins/unassigned', { headers: adminHeaders() });
    if (response.status === 401 || response.status === 419) {
      window.alert('Влез пак като администратор.');
      return;
    }
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    unassignedRows = body.pins || [];
    renderUnassigned();
  }

  openUnassigned.addEventListener('click', () => {
    panel.hidden = true;
    nearbyEl.hidden = true;
    document.getElementById('cities-modal').hidden = true;
    if (composer) {
      composer.hidden = true;
      stopPicking();
    }
    unassignedEl.hidden = false;
    loadUnassigned().catch((err) => {
      unassignedError.hidden = false;
      unassignedError.textContent = err.message || 'Списъкът не се зареди.';
    });
  });

  document.getElementById('unassigned-close').addEventListener('click', () => {
    closeUnassigned();
  });

  map.on('click', async (event) => {
    if (!placingId || picking || placingLock) {
      return;
    }
    const id = placingId;
    placingLock = true;
    try {
      const response = await fetch(`/admin/pins/${id}/place`, {
        method: 'POST',
        headers: { ...adminHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({
          latitude: event.lngLat.lat,
          longitude: event.lngLat.lng,
        }),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const placed = await response.json();
      unassignedRows = unassignedRows.filter((pin) => pin.id !== id);
      pins = [placed, ...pins.filter((item) => item.id !== placed.id)];
      empty.hidden = pins.length !== 0;
      placingId = null;
      map.getCanvas().style.cursor = '';
      unassignedError.hidden = true;
      renderUnassigned();
      drawMarkers();
    } catch (err) {
      unassignedError.hidden = false;
      unassignedError.textContent = err.message || 'Мястото не е записано.';
    } finally {
      placingLock = false;
    }
  });
}
