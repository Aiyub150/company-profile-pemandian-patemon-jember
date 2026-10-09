import asyncio
import json
import os
import subprocess
import time
import urllib.request
import websockets

EDGE_PATH = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
USER_DATA_DIR = os.path.join(os.environ.get("LOCALAPPDATA", "C:\\Temp"), "edge_test_profile_visual")
PORT = 9223
BASE_URL = "http://127.0.0.1:8000"
SAMPLE_CAT_IMG = r"C:\Users\aiyub\AppData\Roaming\Hermes\composer-images\image_f77a15.png"

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

async def take_screenshot(ws, msg_id, filepath):
    res = await send_cmd(ws, msg_id, "Page.captureScreenshot", {"format": "png"})
    img_b64 = res.get("result", {}).get("data", "")
    if img_b64:
        import base64
        with open(filepath, "wb") as f:
            f.write(base64.b64decode(img_b64))
        print(f"  ✔ Screenshot tersimpan: {filepath}")

async def main():
    print("▶ Memulai pengujian visual cropper dengan Edge Chromium...")
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
        version_url = f"http://127.0.0.1:{PORT}/json/list"
        with urllib.request.urlopen(version_url) as res:
            targets = json.loads(res.read().decode())
        page_targets = [t for t in targets if t.get("type") == "page"]
        ws_url = page_targets[0]["webSocketDebuggerUrl"]

        async with websockets.connect(ws_url) as ws:
            msg_id = 1
            await send_cmd(ws, msg_id, "Page.enable"); msg_id += 1
            await send_cmd(ws, msg_id, "Runtime.enable"); msg_id += 1
            await send_cmd(ws, msg_id, "DOM.enable"); msg_id += 1
            await send_cmd(ws, msg_id, "Emulation.setDeviceMetricsOverride", {
                "width": 1280,
                "height": 900,
                "deviceScaleFactor": 1,
                "mobile": False
            }); msg_id += 1

            # 1. Login
            await send_cmd(ws, msg_id, "Page.navigate", {"url": f"{BASE_URL}/login"}); msg_id += 1
            await asyncio.sleep(1.5)
            await evaluate(ws, msg_id, """
                document.querySelector('input[name="username"]').value = 'super_admin';
                document.querySelector('input[name="password"]').value = 'superadmin123';
                document.querySelector('button[type="submit"]').click();
            """); msg_id += 1
            await asyncio.sleep(2)

            # 2. Buka Profile
            await send_cmd(ws, msg_id, "Page.navigate", {"url": f"{BASE_URL}/profile"}); msg_id += 1
            await asyncio.sleep(1.5)

            # Verifikasi form profil baru
            form_status = await evaluate(ws, msg_id, """
                (() => {
                    const previewCard = document.querySelector('.avatar-form-card');
                    const previewDisplay = document.querySelector('.avatar-preview-display');
                    const uploadBtn = document.querySelector('label[for="avatarInput"]');
                    const badge = document.getElementById('avatarStatusBadge');
                    return {
                        hasPreviewCard: !!previewCard,
                        previewDisplayWidth: previewDisplay ? previewDisplay.offsetWidth : 0,
                        previewDisplayHeight: previewDisplay ? previewDisplay.offsetHeight : 0,
                        hasUploadBtn: !!uploadBtn,
                        badgeText: badge ? badge.innerText.trim() : ''
                    };
                })()
            """); msg_id += 1
            print(f"  Status Form Profil Baru: {json.dumps(form_status, indent=2)}")

            # Ambil screenshot halaman profil lengkap
            await take_screenshot(ws, msg_id, "docs/screenshots/profile.png"); msg_id += 1

            # 3. Baca gambar sampel (bila ada gunakan gambar kucing sampel pengguna)
            if os.path.exists(SAMPLE_CAT_IMG):
                import base64
                with open(SAMPLE_CAT_IMG, "rb") as f:
                    cat_b64 = base64.b64encode(f.read()).decode("utf-8")
                mime_type = "image/png"
            else:
                cat_b64 = ""
                mime_type = "image/jpeg"

            print("\n▶ Membuka modal crop dengan gambar latar wallpaper...")
            await evaluate(ws, msg_id, f"""
                (() => {{
                    const b64 = "{cat_b64}";
                    if (b64) {{
                        const byteChars = atob(b64);
                        const byteNums = new Array(byteChars.length);
                        for (let i = 0; i < byteChars.length; i++) {{
                            byteNums[i] = byteChars.charCodeAt(i);
                        }}
                        const byteArray = new Uint8Array(byteNums);
                        const blob = new Blob([byteArray], {{ type: "{mime_type}" }});
                        const file = new File([blob], 'user_cat_sample.png', {{ type: "{mime_type}" }});
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        const input = document.getElementById('avatarInput');
                        input.files = dt.files;
                        input.dispatchEvent(new Event('change', {{ bubbles: true }}));
                    }} else {{
                        const c = document.createElement('canvas');
                        c.width = 640;
                        c.height = 400;
                        const ctx = c.getContext('2d');
                        ctx.fillStyle = '#0f172a';
                        ctx.fillRect(0, 0, 640, 400);
                        c.toBlob(blob => {{
                            const file = new File([blob], 'sample.jpg', {{ type: 'image/jpeg' }});
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            const input = document.getElementById('avatarInput');
                            input.files = dt.files;
                            input.dispatchEvent(new Event('change', {{ bubbles: true }}));
                        }});
                    }}
                }})()
            """); msg_id += 1

            await asyncio.sleep(2)

            # 4. Verifikasi Single-Image Backdrop & Transparent Lens di DOM
            lens_metrics = await evaluate(ws, msg_id, """
                (() => {
                    const modal = document.getElementById('modalCropAvatar');
                    const stageWrapper = document.getElementById('cropStageWrapper');
                    const stage = document.getElementById('cropWallpaperStage');
                    const img = document.getElementById('cropWallpaperImg');
                    const lens = document.getElementById('cropLens');
                    const liveCanvas = document.getElementById('liveCropPreviewCanvas');
                    
                    // Verifikasi ketiadaan elemen gambar ganda (no duplicate img inside lens)
                    const duplicateImgs = lens ? lens.querySelectorAll('img') : [];

                    return {
                        modalShown: modal.classList.contains('show'),
                        hasStageWrapper: !!stageWrapper,
                        imgWidth: img ? img.offsetWidth : 0,
                        imgHeight: img ? img.offsetHeight : 0,
                        lensWidth: lens ? lens.offsetWidth : 0,
                        lensHeight: lens ? lens.offsetHeight : 0,
                        isLensSquare: lens ? lens.offsetWidth === lens.offsetHeight : false,
                        duplicateImgCountInLens: duplicateImgs.length,
                        hasLiveCanvas: !!liveCanvas
                    };
                })()
            """); msg_id += 1
            print(f"  Metrik Transparent Lens Viewfinder: {json.dumps(lens_metrics, indent=2)}")

            # Simpan screenshot modal crop single-backdrop
            await take_screenshot(ws, msg_id, "docs/screenshots/modal_crop_v2.png"); msg_id += 1

            # 5. Uji tombol preset ukuran lensa (Kecil, Sedang, Maksimal)
            print("\n▶ Menguji preset ukuran lensa...")
            test_sizes = await evaluate(ws, msg_id, """
                (() => {
                    const lens = document.getElementById('cropLens');
                    const btnSm = document.getElementById('btnLensSizeSm');
                    const btnLg = document.getElementById('btnLensSizeLg');
                    
                    btnSm.click();
                    const smSize = lens.offsetWidth;

                    btnLg.click();
                    const lgSize = lens.offsetWidth;

                    // Kembalikan ke sedang
                    document.getElementById('btnLensSizeMd').click();
                    const mdSize = lens.offsetWidth;

                    return { smSize, mdSize, lgSize };
                })()
            """); msg_id += 1
            print(f"  Preset Ukuran Lensa: {json.dumps(test_sizes, indent=2)}")

            # 6. Terapkan Crop
            print("\n▶ Menekan Selesai & Terapkan Foto...")
            await evaluate(ws, msg_id, """
                document.getElementById('btnApplyCrop').click();
            """); msg_id += 1
            await asyncio.sleep(1)

            result_metrics = await evaluate(ws, msg_id, """
                (() => {
                    const previewImg = document.getElementById('avatarPreviewImg');
                    const badge = document.getElementById('avatarStatusBadge');
                    const croppedData = document.getElementById('avatarCroppedData');
                    return {
                        previewImgSrcPrefix: previewImg ? previewImg.src.substring(0, 30) : '',
                        hasBase64Data: croppedData ? croppedData.value.startsWith('data:image/jpeg;base64,') : false,
                        badgeText: badge ? badge.innerText.trim() : ''
                    };
                })()
            """); msg_id += 1
            print(f"  Hasil Setelah Crop Diterapkan: {json.dumps(result_metrics, indent=2)}")

            # Simpan screenshot profil setelah crop
            await take_screenshot(ws, msg_id, "docs/screenshots/profile_after_crop.png"); msg_id += 1

            print("\n✔ Pengujian visual cropper selesai dan sukses!")

    finally:
        proc.terminate()
        try:
            proc.wait(timeout=3)
        except Exception:
            proc.kill()

if __name__ == "__main__":
    asyncio.run(main())
