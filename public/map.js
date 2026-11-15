const SOFIA_BOUNDS = L.latLngBounds([42.6577, 23.2819], [42.7377, 23.3619]);
const CATEGORIES = {
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
const TILES = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}';

const map = L.map('map', { maxZoom: 19, minZoom: 3, zoomControl: true });
L.tileLayer(TILES, {
  maxZoom: 19,
  maxNativeZoom: 16,
  attribution: 'Карта: Esri',
}).addTo(map);
let viewTouched = false;
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
const zoomControl = document.querySelector('.leaflet-control-zoom');
if (zoomControl) {
  zoomControl.addEventListener('click', () => {
    viewTouched = true;
  });
}

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

function showBounds(region) {
  map.fitBounds([
    [region.latitude - region.latitudeDelta / 2, region.longitude - region.longitudeDelta / 2],
    [region.latitude + region.latitudeDelta / 2, region.longitude + region.longitudeDelta / 2],
  ]);
}

function drawMarkers() {
  markers.forEach((marker) => marker.remove());
  markers = [];
  const items = groupPins(pins, currentRegion());

  items.clusters.forEach((cluster) => {
    const width = Math.max(46, 16 + String(cluster.count).length * 12);
    const icon = L.divIcon({
      className: 'blip-icon',
      html: `<div class="count" style="width:${width}px">(${cluster.count})</div>`,
      iconSize: [width, 46],
      iconAnchor: [width / 2, 23],
    });
    const marker = L.marker([cluster.latitude, cluster.longitude], { icon, zIndexOffset: 500 });
    marker.on('click', () => showBounds(zoomRegion(currentRegion(), cluster)));
    marker.addTo(map);
    markers.push(marker);
  });

  items.singles.forEach((pin) => {
    const kind = categoryLabel(pin.category);
    const base = (pin.image_url ? 132 : 80) + (kind ? 18 : 0);
    const height = base + (pin.stackOffset || 0);
    const image = safeUrl(pin.image_url)
      ? `<img src="${safeUrl(pin.image_url)}" alt="">`
      : '';
    const kindHtml = kind ? `<small>${escapeHtml(kind)}</small>` : '';
    const icon = L.divIcon({
      className: 'blip-icon',
      html: `<div class="bubble-wrap" style="height:${base}px;padding-bottom:${pin.stackOffset || 0}px"><div class="bubble">${image}<span>${escapeHtml(pin.message)}</span>${kindHtml}</div><div class="tail"></div></div>`,
      iconSize: [168, height],
      iconAnchor: [84, height],
    });
    const marker = L.marker([pin.latitude, pin.longitude], { icon, zIndexOffset: (pin.stackOffset || 0) + 1 });
    marker.on('click', () => openPin(pin));
    marker.addTo(map);
    markers.push(marker);
  });
}

async function loadPins() {
  if (!map._loaded) {
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
    pins = (body.pins || []).filter((pin) => new Date(pin.expires_at).getTime() > now);
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
  document.getElementById('panel-time').textContent = timeLeft(selected.expires_at);
  const photo = document.getElementById('panel-photo');
  photo.hidden = !safeUrl(selected.image_url);
  photo.src = photo.hidden ? '' : selected.image_url;

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

  document.getElementById('panel-sent').hidden = selected.mine || !selected.replied;
  document.getElementById('reply-form').hidden = selected.mine || selected.replied;
  document.getElementById('report').hidden = selected.mine || selected.reported;
  document.getElementById('reported').hidden = selected.mine || !selected.reported;
  document.getElementById('remove').hidden = !selected.mine;
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
  selected = null;
  replies = [];
  panel.hidden = true;
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
      meta.textContent = `${formatDistance(pin.km)} · ${timeLeft(pin.expires_at)}`;
      copy.append(meta);
      button.append(copy);
      button.addEventListener('click', () => {
        nearbyEl.hidden = true;
        map.panTo([pin.latitude, pin.longitude]);
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

document.getElementById('open-list').addEventListener('click', () => {
  listCenter = map.getCenter();
  panel.hidden = true;
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
    (position) => map.panTo([position.coords.latitude, position.coords.longitude]),
    () => window.alert('Blip не успя да прочете къде си.'),
  );
});

banner.addEventListener('click', loadPins);

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closePanel();
    nearbyEl.hidden = true;
  }
});

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
    map.panTo([pin.latitude, pin.longitude]);
    note.close();
  };
}

toast.addEventListener('click', () => {
  if (toastPin) {
    map.panTo([toastPin.latitude, toastPin.longitude]);
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

function showCity(latitude, longitude) {
  const span = 0.08;
  map.fitBounds([
    [latitude - span / 2, longitude - span / 2],
    [latitude + span / 2, longitude + span / 2],
  ]);
}

let holdLink = /^\d+$/.test(new URLSearchParams(window.location.search).get('pin') || '');

function revealIfFar(point) {
  if (holdLink || !point) {
    return;
  }
  if (viewTouched && map._loaded) {
    return;
  }
  if (map._loaded) {
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
    map.fitBounds([
      [Number(pin.latitude) - span / 2, Number(pin.longitude) - span / 2],
      [Number(pin.latitude) + span / 2, Number(pin.longitude) + span / 2],
    ]);
    openPin(pin);
    return true;
  } catch {
    return false;
  }
}

async function openOnCity() {
  const gpsDone = followCity();
  const ipTask = lookupIpCity(
    'https://get.geojs.io/v1/ip/geo.json',
    (body) => cityPoint(body.latitude, body.longitude),
  ).then((point) => point || lookupIpCity(
    'https://ipwho.is/',
    (body) => (body && body.success ? cityPoint(body.latitude, body.longitude) : null),
  ));

  if (holdLink) {
    const opened = await openLinkedPin();
    if (opened) {
      return;
    }
    holdLink = false;
  }

  const firstGps = await Promise.race([
    gpsDone,
    new Promise((resolve) => setTimeout(() => resolve(undefined), 900)),
  ]);
  if (firstGps) {
    revealIfFar(firstGps);
    return;
  }

  const ip = await Promise.race([
    ipTask,
    new Promise((resolve) => setTimeout(() => resolve(null), 1600)),
  ]);
  if (!map._loaded) {
    if (ip) {
      showCity(ip.lat, ip.lng);
    }
  }

  const gps = await gpsDone;
  if (map._loaded) {
    return;
  }
  if (gps) {
    showCity(gps.lat, gps.lng);
    return;
  }
  const lateIp = ip || await ipTask;
  if (lateIp) {
    showCity(lateIp.lat, lateIp.lng);
  } else if (!map._loaded) {
    map.fitBounds(SOFIA_BOUNDS);
  }
}

setInterval(() => loadPins(), 20000);
setInterval(watchNearby, 20000);
openOnCity();
watchNearby();
