<?php
/**
 * admin/partials/wysiwyg.php
 * ------------------------------------------------------------------
 * ویجت ویرایشگر WYSIWYG (TinyMCE 6 self-hosted) + Media Library modal
 * فقط یک include ساده در فرم‌های ادمین:
 *   <?php include __DIR__ . '/../partials/wysiwyg.php'; ?>
 *   <textarea name="description" data-wysiwyg class="d-none">…</textarea>
 * ------------------------------------------------------------------
 */
?>
<!-- TinyMCE 6 self-hosted (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js" referrerpolicy="origin"></script>

<!-- پنل ابزار حرفه‌ای -->
<script>
window.WYSIWYG_DEFAULTS = {
    selector: 'textarea[data-wysiwyg]',
    height: 420,
    menubar: 'file edit view insert format tools table',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount emoticons directionality pagebreak nonbreaking save',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | ltr rtl | code fullscreen preview | save',
    toolbar_mode: 'sliding',
    image_advtab: true,
    image_uploadtab: true,
    branding: false,
    promotion: false,
    statusbar: true,
    elementpath: true,
    resize: 'vertical',
    language: 'fa_IR',
    directionality: 'rtl',
    content_style: 'body{font-family:tahoma,arial,sans-serif;direction:rtl;text-align:right;font-size:14px;line-height:1.7} img{max-width:100%;height:auto;border-radius:6px}',
    entity_encoding: 'raw',
    entities: '160,nbsp,161,iexcl,162,cent,163,pound,164,curren,165,yen,166,brvbar,167,sect,168,uml,169,copy,170,ordf,171,laquo,172,not,173,shy,174,reg,175,macr,176,deg,177,plusmn,178,sup2,179,sup3,180,acute,181,micro,182,para,183,middot,184,cedil,185,sup1,186,ordm,187,raquo,188,frac14,189,frac12,190,frac34,191,iquest,192,Agrave,194,Acirc,195,Atilde,196,Auml,197,Aring,198,AElig,199,Ccedil,200,Egrave,202,Ecirc,203,Euml,204,Igrave,206,Icirc,207,Iuml,209,Ntilde,210,Ograve,212,Ocirc,213,Otilde,214,Ouml,215,times,216,Oslash,217,Ugrave,219,Ucirc,220,Uuml,221,Yacute,223,szlig,224,agrave,226,acirc,227,atilde,228,auml,229,aring,230,aelig,231,ccedil,232,egrave,234,ecirc,235,euml,236,igrave,238,icirc,239,iuml,241,ntilde,242,ograve,244,ocirc,245,otilde,246,ouml,247,divide,248,oslash,249,ugrave,251,ucirc,252,uuml,253,yacute,255,yuml,402,fnof,913,Alpha,914,Beta,915,Gamma,916,Delta,917,Epsilon,918,Zeta,919,Eta,920,Theta,921,Iota,922,Kappa,923,Lambda,924,Mu,925,Nu,926,Xi,927,Omicron,928,Pi,929,Rho,931,Sigma,932,Tau,933,Upsilon,934,Phi,935,Chi,936,Psi,937,Omega,945,alpha,946,beta,947,gamma,948,delta,949,epsilon,950,zeta,951,eta,952,theta,953,iota,954,kappa,955,lambda,956,mu,957,nu,958,xi,959,omicron,960,pi,961,rho,962,sigmaf,963,sigma,964,tau,965,upsilon,966,phi,967,chi,968,psi,969,omega,977,thetasym,978,upsih,979,piv,8226,bull,8230,hellip,8242,prime,8243,Prime,8254,oline,8260,frasl,8274,weierp,8465,image,8476,real,8482,trade,8501,alefsym,8592,larr,8593,uarr,8594,rarr,8595,darr,8596,harr,8629,crarr,8656,lArr,8657,uArr,8658,rArr,8659,dArr,8660,hArr,8704,forall,8706,exist,8707,empty,8708,nabla,8709,isin,8711,notin,8712,ni,8713,prod,8715,sum,8719,sube,8720,sup,8721,nsub,8722,sube,8723,sup,8724,sube,8725,sube,8726,sube,8727,sube,8728,sube,8729,sube,8730,radi,8733,prop,8734,infin,8736,ang,8743,and,8744,or,8745,cap,8746,cup,8747,int,8756,there4,8764,sim,8773,cong,8776,asymp,8800,ne,8801,equiv,8804,le,8805,ge,8834,sub,8835,sup,8836,nsub,8837,sube,8838,sup,8839,sube,8853,oplus,8855,otimes,8869,perp,8901,sdot,8968,lceil,8969,rceil,8970,lfloor,8971,rfloor,9001,lang,9002,rang,9674,loz,9824,spades,9827,clubs,9829,hearts,9642,diams',
    // لینک تصویر آپلود
    images_upload_handler: function (blob, progress) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?= BASE_URL ?>/admin/upload_image.php');
            xhr.upload.onprogress = function (e) { progress(e.loaded / e.total * 100); };
            xhr.onload = function () {
                if (xhr.status < 200 || xhr.status >= 300) return reject('خطا در آپلود');
                try { const r = JSON.parse(xhr.responseText); r.location ? resolve(r.location) : reject(r.error || 'خطا'); }
                catch (e) { reject('پاسخ نامعتبر'); }
            };
            xhr.onerror = function () { reject('خطای شبکه'); };
            const fd = new FormData();
            fd.append('file', blob, blob.filename || ('img-' + Date.now() + '.jpg'));
            fd.append('csrf', '<?= e(csrf_token()) ?>');
            xhr.send(fd);
        });
    },
    // فایل لینک و مدیا
    file_picker_callback: function (cb, value, meta) {
        openMediaLibrary(function (url, alt) {
            cb(url, { title: alt, alt: alt });
        }, meta.filetype === 'media' ? 'media' : 'image');
    },
    setup: function (editor) {
        // دکمهٔ ذخیرهٔ خودکار
        editor.ui.registry.addButton('save', {
            text: 'ذخیره پیش‌نویس',
            icon: 'save',
            onAction: function () {
                const form = editor.targetElm.closest('form');
                if (!form) return;
                form.querySelector('input[name="__autosave"]').value = '1';
                form.submit();
            }
        });
    }
};
tinymce.init(window.WYSIWYG_DEFAULTS);
</script>

<!-- Media Library Modal -->
<div id="mediaLibraryModal" class="media-modal" style="display:none;">
    <div class="media-modal__panel">
        <div class="media-modal__head">
            <h3>📁 کتابخانهٔ رسانه</h3>
            <button type="button" class="media-modal__close" data-close>×</button>
        </div>
        <div class="media-modal__tabs">
            <button type="button" class="media-modal__tab is-active" data-tab="browse">مرور</button>
            <button type="button" class="media-modal__tab" data-tab="upload">آپلود</button>
        </div>
        <div class="media-modal__body">
            <div data-pane="browse" class="is-active">
                <div class="media-modal__filters">
                    <input type="search" data-search placeholder="جستجو...">
                    <select data-folder>
                        <option value="">همهٔ پوشه‌ها</option>
                    </select>
                </div>
                <div class="media-modal__grid" data-grid></div>
                <div class="media-modal__empty" hidden>موردی یافت نشد.</div>
            </div>
            <div data-pane="upload" hidden>
                <div class="media-modal__drop" data-drop>
                    <p>📤 فایل را اینجا بکشید یا کلیک کنید</p>
                    <input type="file" multiple accept="image/*,application/pdf" data-upload>
                </div>
                <div class="media-modal__progress" data-progress hidden></div>
            </div>
        </div>
    </div>
</div>
<script>
/* مدیریت Media Library */
let mediaCallback = null;
function openMediaLibrary(cb, kind) {
    mediaCallback = cb;
    document.getElementById('mediaLibraryModal').style.display = 'flex';
    loadMediaList();
}
function closeMediaLibrary() {
    document.getElementById('mediaLibraryModal').style.display = 'none';
    mediaCallback = null;
}
function loadMediaList(q, folder) {
    const url = new URL('<?= BASE_URL ?>/admin/media_api.php', location.origin);
    url.searchParams.set('action', 'list');
    if (q) url.searchParams.set('q', q);
    if (folder) url.searchParams.set('folder', folder);
    fetch(url).then(r => r.json()).then(renderMediaGrid);
}
function renderMediaGrid(items) {
    const grid = document.querySelector('[data-grid]');
    const empty = document.querySelector('.media-modal__empty');
    grid.innerHTML = '';
    if (!items.length) { empty.hidden = false; return; } else { empty.hidden = true; }
    items.forEach(it => {
        const el = document.createElement('div');
        el.className = 'media-item';
        el.innerHTML = `<img src="${it.url}" alt="${it.alt||''}"><div class="media-item__name">${it.name}</div>`;
        el.onclick = () => { if (mediaCallback) mediaCallback(it.url, it.alt || it.name); closeMediaLibrary(); };
        grid.appendChild(el);
    });
}
document.addEventListener('click', e => {
    if (e.target.matches('[data-close]')) closeMediaLibrary();
    if (e.target.matches('[data-tab]')) {
        document.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('is-active', b === e.target));
        document.querySelectorAll('[data-pane]').forEach(p => p.hidden = p.dataset.pane !== e.target.dataset.tab);
    }
});
document.addEventListener('input', e => {
    if (e.target.matches('[data-search]')) loadMediaList(e.target.value);
    if (e.target.matches('[data-folder]')) loadMediaList(null, e.target.value);
});
document.addEventListener('change', e => {
    if (e.target.matches('[data-upload]')) uploadMedia(e.target.files);
});
function uploadMedia(files) {
    if (!files || !files.length) return;
    const fd = new FormData();
    Array.from(files).forEach(f => fd.append('files[]', f));
    fd.append('csrf', '<?= e(csrf_token()) ?>');
    const bar = document.querySelector('[data-progress]');
    bar.hidden = false; bar.textContent = 'در حال آپلود...';
    fetch('<?= BASE_URL ?>/admin/media_api.php?action=upload', { method: 'POST', body: fd })
        .then(r => r.json()).then(r => {
            bar.textContent = r.ok ? '✓ آپلود شد' : '✗ خطا: ' + (r.error || '');
            if (r.ok) setTimeout(() => { bar.hidden = true; loadMediaList(); }, 1500);
        });
}
</script>
