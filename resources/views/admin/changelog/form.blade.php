<form class="card" id="changelog-form" method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <label for="title">Заглавие</label>
    <input id="title" name="title" value="{{ old('title', $entry->title) }}" maxlength="120" required>

    <label for="changelog-body">Текст</label>
    <p class="meta">Markdown. Удебелен текст, списък, цитат и линк се четат еднакво на сайта и в Discord. Заглавието отива в Discord като удебелен линк към страницата.</p>
    <div class="editor">
        <div class="editor-bar">
            <button type="button" data-wrap="**">Удебелен</button>
            <button type="button" data-wrap="*">Курсив</button>
            <button type="button" data-prefix="## ">Заглавие</button>
            <button type="button" data-prefix="- ">Списък</button>
            <button type="button" data-prefix="> ">Цитат</button>
            <button type="button" data-link="1">Линк</button>
        </div>
        <textarea id="changelog-body" name="body" maxlength="8000" required>{{ old('body', $entry->body) }}</textarea>
        <div class="preview-grid">
            <div>
                <p class="meta">Сайт</p>
                <div class="preview-site md" id="preview-html"></div>
            </div>
            <div>
                <p class="meta">Discord</p>
                <pre id="preview-discord"></pre>
            </div>
        </div>
    </div>

    <input type="hidden" name="publish" value="0">
    <label class="switch">
        <input type="checkbox" name="publish" value="1" @checked((string) old('publish', $entry->published_at ? '1' : ($entry->exists ? '0' : '1')) === '1')>
        <i aria-hidden="true"></i>
        <span>Публикувай в сайта и в Discord</span>
    </label>

    <div class="form-actions">
        <button type="submit">Запази</button>
    </div>
</form>

<style>
    .editor textarea {
        width: 100%;
        min-height: 220px;
        margin-top: 8px;
        border: 0;
        border-radius: 14px;
        background: #fff;
        padding: 12px 14px;
        font: inherit;
        color: var(--ink);
        resize: vertical;
    }

    .editor-bar { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }

    .editor-bar button {
        border: 0;
        border-radius: 999px;
        background: #fff;
        color: var(--ink);
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        padding: 6px 10px;
        cursor: pointer;
    }

    .preview-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 14px;
    }

    .preview-site, #preview-discord {
        min-height: 120px;
        margin: 6px 0 0;
        padding: 12px 14px;
        border-radius: 14px;
        background: #fff;
        white-space: normal;
    }

    #preview-discord {
        white-space: pre-wrap;
        font: inherit;
        font-size: 14px;
    }

    .md p { margin: 0 0 10px; }
    .md h3, .md h4 { margin: 12px 0 6px; font-size: 16px; }
    .md ul, .md ol { margin: 0 0 10px; padding-left: 18px; }
    .md a { color: var(--pin); }
    .md code { font-size: 13px; }
    .md pre { margin: 0 0 10px; padding: 10px; border-radius: 10px; background: var(--paper); overflow: auto; }
    .md blockquote { margin: 0 0 10px; padding-left: 10px; border-left: 3px solid var(--line); color: var(--muted); }

    @media (max-width: 800px) {
        .preview-grid { grid-template-columns: 1fr; }
    }
</style>

<script>
    (function () {
        var form = document.getElementById('changelog-form');
        var title = document.getElementById('title');
        var body = document.getElementById('changelog-body');
        var html = document.getElementById('preview-html');
        var discord = document.getElementById('preview-discord');
        var token = form.querySelector('input[name=_token]').value;
        var timer = null;

        function wrap(mark) {
            var start = body.selectionStart;
            var end = body.selectionEnd;
            var selected = body.value.slice(start, end) || 'текст';
            body.setRangeText(mark + selected + mark, start, end, 'select');
            body.focus();
            schedule();
        }

        function prefix(mark) {
            var start = body.selectionStart;
            var end = body.selectionEnd;
            var selected = body.value.slice(start, end) || 'текст';
            var next = selected.split('\n').map(function (line) {
                return mark + line;
            }).join('\n');
            body.setRangeText(next, start, end, 'end');
            body.focus();
            schedule();
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(preview, 200);
        }

        function preview() {
            fetch(@json(route('admin.changelog.preview')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    title: title.value,
                    body: body.value,
                    id: @json($entry->id)
                })
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                html.innerHTML = data.html || '';
                discord.textContent = data.discord || '';
            }).catch(function () {});
        }

        form.querySelectorAll('.editor-bar button').forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.dataset.wrap) wrap(button.dataset.wrap);
                else if (button.dataset.prefix) prefix(button.dataset.prefix);
                else body.setRangeText('[текст](https://)', body.selectionStart, body.selectionEnd, 'select'), body.focus(), schedule();
            });
        });

        title.addEventListener('input', schedule);
        body.addEventListener('input', schedule);
        preview();
    })();
</script>
