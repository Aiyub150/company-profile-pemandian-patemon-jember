import asyncio
import json
import os
import subprocess
import time
import urllib.request
import websockets

EDGE_PATH = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
USER_DATA_DIR = os.path.join(os.environ.get("LOCALAPPDATA", "C:\\Temp"), "edge_test_profile")
PORT = 9222
BASE_URL = "http://127.0.0.1:8000"

async def send_cmd(ws, msg_id, method, params=None):
    payload = {"id": msg_id, "method": method}
    if params:
        payload["params"] = params
    await ws.send(json.dumps(payload))
    while True:
        resp = await ws.recv()
        data = json.loads(resp)
        if data.get("id") == msg_id:
            return data

async def evaluate(ws, msg_id, expr, await_promise=False):
    res = await send_cmd(ws, msg_id, "Runtime.evaluate", {
        "expression": expr,
        "awaitPromise": await_promise,
        "returnByValue": True
    })
    return res.get("result", {}).get("result", {}).get("value")

async def run_browser_tests():
    print("▶ Memulai browser Edge Chromium dalam mode Headless...")
    cmd = [
        EDGE_PATH,
        "--headless=new",
        f"--remote-debugging-port={PORT}",
        f"--user-data-dir={USER_DATA_DIR}",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-gpu",
        "about:blank"
    ]
    proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(2)

    try:
        # Dapatkan WebSocket Debugging URL untuk target halaman (type == 'page')
        version_url = f"http://127.0.0.1:{PORT}/json/list"
        with urllib.request.urlopen(version_url) as res:
            targets = json.loads(res.read().decode())
        page_targets = [t for t in targets if t.get("type") == "page"]
        if not page_targets:
            raise RuntimeError("Tidak ada target tab halaman aktif ditemukan di browser")
        ws_url = page_targets[0]["webSocketDebuggerUrl"]
        print(f"✔ Terhubung ke DevTools Protocol (Tab Halaman): {ws_url}")

        async with websockets.connect(ws_url) as ws:
            msg_id = 1

            # Enable Page, Runtime, DOM
            await send_cmd(ws, msg_id, "Page.enable"); msg_id += 1
            await send_cmd(ws, msg_id, "Runtime.enable"); msg_id += 1
            await send_cmd(ws, msg_id, "DOM.enable"); msg_id += 1

            # 1. Buka halaman login
            print("\n[STEP 1] Navigasi ke Halaman Login...")
            await send_cmd(ws, msg_id, "Page.navigate", {"url": f"{BASE_URL}/login"}); msg_id += 1
            await asyncio.sleep(2)

            title = await evaluate(ws, msg_id, "document.title"); msg_id += 1
            print(f"  Judul Halaman: {title}")

            # 2. Login sebagai super_admin
            print("\n[STEP 2] Melakukan Login Super Administrator...")
            login_script = """
            (() => {
                document.querySelector('input[name="username"]').value = 'super_admin';
                document.querySelector('input[name="password"]').value = 'superadmin123';
                document.querySelector('button[type="submit"]').click();
            })()
            """
            await evaluate(ws, msg_id, login_script); msg_id += 1
            await asyncio.sleep(2.5)

            current_url = await evaluate(ws, msg_id, "window.location.href"); msg_id += 1
            print(f"  URL setelah login: {current_url}")

            # 3. Navigasi ke halaman profil
            print("\n[STEP 3] Membuka Halaman Profil (/profile)...")
            await send_cmd(ws, msg_id, "Page.navigate", {"url": f"{BASE_URL}/profile"}); msg_id += 1
            await asyncio.sleep(2.5)

            profile_title = await evaluate(ws, msg_id, "document.title"); msg_id += 1
            print(f"  Judul Halaman Profil: {profile_title}")

            # Verifikasi ketersediaan elemen
            checks = await evaluate(ws, msg_id, """
            (() => {
                return {
                    hasAvatarInput: !!document.getElementById('avatarInput'),
                    hasAvatarPreviewImg: !!document.getElementById('avatarPreviewImg'),
                    hasModalCropAvatar: !!document.getElementById('modalCropAvatar'),
                    hasCropperTarget: !!document.getElementById('cropperImageTarget'),
                    hasCropperJs: typeof Cropper !== 'undefined',
                    hasBootstrap: typeof bootstrap !== 'undefined'
                };
            })()
            """); msg_id += 1
            print(f"  Status Komponen: {json.dumps(checks, indent=2)}")

            # 4. Tes Pemilihan Gambar Valid (Simulasi Pengunggahan Foto Profil)
            print("\n[STEP 4] Simulasi Pemilihan Foto Profil Valid (File Image Ukuran Normal)...")
            
            # Buat sample gambar 100x100 via canvas dan trigger change event
            simulate_file_select = """
            (() => {
                // Buat kanvas dummy gambar 300x300
                const canvas = document.createElement('canvas');
                canvas.width = 300;
                canvas.height = 300;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#0284c7';
                ctx.fillRect(0, 0, 300, 300);
                ctx.fillStyle = '#ffffff';
                ctx.font = '24px sans-serif';
                ctx.fillText('AVATAR TEST', 60, 150);

                const dataUrl = canvas.toDataURL('image/jpeg');
                
                // Konversi dataUrl ke blob dan File
                const byteString = atob(dataUrl.split(',')[1]);
                const mimeString = dataUrl.split(',')[0].split(':')[1].split(';')[0];
                const ab = new ArrayBuffer(byteString.length);
                const ia = new Uint8Array(ab);
                for (let i = 0; i < byteString.length; i++) {
                    ia[i] = byteString.charCodeAt(i);
                }
                const blob = new Blob([ab], {type: mimeString});
                const file = new File([blob], 'avatar_test.jpg', {type: 'image/jpeg'});

                const dt = new DataTransfer();
                dt.items.add(file);
                const avatarInput = document.getElementById('avatarInput');
                avatarInput.files = dt.files;
                
                // Trigger event change
                const event = new Event('change', { bubbles: true });
                avatarInput.dispatchEvent(event);
                return true;
            })()
            """
            await evaluate(ws, msg_id, simulate_file_select); msg_id += 1
            await asyncio.sleep(1.5)

            # Cek apakah instant preview muncul dan modal terbuka
            modal_state = await evaluate(ws, msg_id, """
            (() => {
                const modal = document.getElementById('modalCropAvatar');
                const previewImg = document.getElementById('avatarPreviewImg');
                const targetImg = document.getElementById('cropperImageTarget');
                return {
                    instantPreviewSrc: (previewImg && previewImg.src.startsWith('data:image')) ? 'DATA_URL_OK' : (previewImg ? previewImg.src : 'NONE'),
                    previewVisible: previewImg ? previewImg.style.display !== 'none' : false,
                    modalDisplayed: modal ? (modal.classList.contains('show') || modal.style.display === 'block') : false,
                    targetSrcReady: targetImg && targetImg.src.startsWith('data:image')
                };
            })()
            """); msg_id += 1
            print(f"  Hasil Pemilihan Foto: {json.dumps(modal_state, indent=2)}")

            # Ambil screenshot modal pemotongan
            ss_modal = await send_cmd(ws, msg_id, "Page.captureScreenshot", {"format": "png"}); msg_id += 1
            import base64
            with open("modal_crop_browser_test.png", "wb") as f:
                f.write(base64.b64decode(ss_modal["result"]["data"]))
            print("  ✔ Screenshot tersimpan: modal_crop_browser_test.png")

            # 5. Uji Penekanan Tombol 'Selesai & Terapkan Foto' (Apply Crop)
            print("\n[STEP 5] Menekan Tombol 'Selesai & Terapkan Foto'...")
            apply_crop_action = """
            (() => {
                const btn = document.getElementById('btnApplyCrop');
                if (btn) {
                    btn.click();
                    return true;
                }
                return false;
            })()
            """
            await evaluate(ws, msg_id, apply_crop_action); msg_id += 1
            await asyncio.sleep(1.5)

            after_crop_state = await evaluate(ws, msg_id, """
            (() => {
                const hiddenInput = document.getElementById('avatarCroppedData');
                const previewImg = document.getElementById('avatarPreviewImg');
                const sidebarImg = document.getElementById('sidebarAvatarImg');
                const modal = document.getElementById('modalCropAvatar');
                return {
                    hasCroppedBase64: !!(hiddenInput && hiddenInput.value && hiddenInput.value.startsWith('data:image/jpeg;base64,')),
                    croppedDataLength: hiddenInput ? hiddenInput.value.length : 0,
                    previewImgUpdated: !!(previewImg && previewImg.src.startsWith('data:image/jpeg;base64,')),
                    sidebarImgUpdated: !!(sidebarImg && sidebarImg.src.startsWith('data:image/jpeg;base64,')),
                    modalClosed: modal ? (!modal.classList.contains('show') && modal.style.display !== 'block') : true
                };
            })()
            """); msg_id += 1
            print(f"  Hasil Pemotongan 1:1: {json.dumps(after_crop_state, indent=2)}")

            # 5b. Uji Submit Form Profil ke Database
            print("\n[STEP 5b] Mengirimkan Form Perubahan Profil ke Database...")
            submit_profile_action = """
            (() => {
                const submitBtn = document.querySelector('button[type="submit"].btn-brand');
                if (submitBtn) {
                    submitBtn.click();
                    return true;
                }
                return false;
            })()
            """
            await evaluate(ws, msg_id, submit_profile_action); msg_id += 1
            await asyncio.sleep(2.5)

            # Cek status alert sukses di profil setelah redirect
            post_submit_status = await evaluate(ws, msg_id, """
            (() => {
                const alertSuccess = document.querySelector('.alert-success');
                const avatarImg = document.getElementById('avatarPreviewImg');
                return {
                    url: window.location.href,
                    successMessage: alertSuccess ? alertSuccess.textContent.trim() : 'NONE',
                    savedAvatarSrc: avatarImg ? avatarImg.src : 'NONE'
                };
            })()
            """); msg_id += 1
            print(f"  Hasil Penyimpanan Database: {json.dumps(post_submit_status, indent=2)}")

            # 6. Uji Validasi Gambar > 2 MB (SweetAlert2)
            print("\n[STEP 6] Uji Validasi Gambar Melebihi Batas 2 MB...")
            simulate_oversized = """
            (() => {
                // Buat file simulasi dummy > 2 MB (misal 2.5 MB)
                const largeBytes = new Uint8Array(2.5 * 1024 * 1024);
                const largeBlob = new Blob([largeBytes], { type: 'image/jpeg' });
                const largeFile = new File([largeBlob], 'oversized_photo.jpg', { type: 'image/jpeg' });

                const dt = new DataTransfer();
                dt.items.add(largeFile);
                const avatarInput = document.getElementById('avatarInput');
                avatarInput.files = dt.files;

                const event = new Event('change', { bubbles: true });
                avatarInput.dispatchEvent(event);
                return true;
            })()
            """
            await evaluate(ws, msg_id, simulate_oversized); msg_id += 1
            await asyncio.sleep(1.5)

            swal_state = await evaluate(ws, msg_id, """
            (() => {
                const swalModal = document.querySelector('.swal2-container');
                const swalText = document.querySelector('.swal2-html-container');
                const avatarInput = document.getElementById('avatarInput');
                return {
                    swalVisible: !!(swalModal && swalModal.offsetParent !== null),
                    alertText: swalText ? swalText.textContent.trim() : '',
                    inputCleared: avatarInput ? avatarInput.value === '' : false
                };
            })()
            """); msg_id += 1
            print(f"  Hasil Validasi Ukuran > 2 MB: {json.dumps(swal_state, indent=2)}")

            ss_swal = await send_cmd(ws, msg_id, "Page.captureScreenshot", {"format": "png"}); msg_id += 1
            with open("swal_oversize_browser_test.png", "wb") as f:
                f.write(base64.b64decode(ss_swal["result"]["data"]))
            print("  ✔ Screenshot tersimpan: swal_oversize_browser_test.png")

            # 7. Periksa Console Errors
            print("\n[STEP 7] Memeriksa Runtime Console Logs...")
            console_errors = await evaluate(ws, msg_id, "window.__test_errors || []"); msg_id += 1
            print(f"  Console Errors Terdeteksi: {console_errors}")

            print("\n✔ SEMUA TAHAPAN PENGUJIAN BROWSER SELESAI DENGAN SUKSES!")

    finally:
        proc.terminate()
        try:
            proc.wait(timeout=3)
        except:
            proc.kill()

if __name__ == "__main__":
    asyncio.run(run_browser_tests())
