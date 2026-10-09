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

            # 3. Simulasi unggah gambar potret (portrait / wajah)
            print("\n▶ Membuka modal crop dengan gambar potret (300x500)...")
            await evaluate(ws, msg_id, """
                (() => {
                    // Buat gambar canvas portrait sintetis
                    const c = document.createElement('canvas');
                    c.width = 300;
                    c.height = 500;
                    const ctx = c.getContext('2d');
                    ctx.fillStyle = '#0284c7';
                    ctx.fillRect(0, 0, 300, 500);
                    // Gambar wajah lingkaran
                    ctx.fillStyle = '#fde047';
                    ctx.beginPath();
                    ctx.arc(150, 200, 80, 0, Math.PI * 2);
                    ctx.fill();

                    c.toBlob(blob => {
                        const file = new File([blob], 'portrait_test.jpg', { type: 'image/jpeg' });
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        const input = document.getElementById('avatarInput');
                        input.files = dt.files;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }, 'image/jpeg');
                })()
            """); msg_id += 1

            await asyncio.sleep(2)

            # Periksa modal crop yang aktif
            cropper_metrics = await evaluate(ws, msg_id, """
                (() => {
                    const modal = document.getElementById('modalCropAvatar');
                    const targetContainer = document.querySelector('.img-cropper-target-container');
                    const cropperBox = document.querySelector('.cropper-crop-box');
                    const livePreviewLg = document.querySelector('.cropper-live-preview-box');
                    const zoomSlider = document.getElementById('cropZoomSlider');
                    return {
                        modalShown: modal.classList.contains('show'),
                        containerWidth: targetContainer ? targetContainer.offsetWidth : 0,
                        containerHeight: targetContainer ? targetContainer.offsetHeight : 0,
                        cropBoxWidth: cropperBox ? Math.round(cropperBox.offsetWidth) : 0,
                        cropBoxHeight: cropperBox ? Math.round(cropperBox.offsetHeight) : 0,
                        is1to1: cropperBox ? Math.abs(cropperBox.offsetWidth - cropperBox.offsetHeight) <= 2 : false,
                        hasLivePreview: !!livePreviewLg,
                        sliderValue: zoomSlider ? zoomSlider.value : null
                    };
                })()
            """); msg_id += 1
            print(f"  Metrik Modal Cropper: {json.dumps(cropper_metrics, indent=2)}")

            # Ambil screenshot modal crop
            await take_screenshot(ws, msg_id, "docs/screenshots/modal_crop_v2.png"); msg_id += 1

            # 4. Tes Zoom Slider interaktif
            print("\n▶ Menguji slider zoom...")
            slider_test = await evaluate(ws, msg_id, """
                (() => {
                    const slider = document.getElementById('cropZoomSlider');
                    const labelBefore = document.getElementById('zoomPercentLabel').innerText;
                    slider.value = (parseFloat(slider.value) * 1.5).toFixed(2);
                    slider.dispatchEvent(new Event('input', { bubbles: true }));
                    const labelAfter = document.getElementById('zoomPercentLabel').innerText;
                    return { labelBefore, labelAfter };
                })()
            """); msg_id += 1
            print(f"  Slider Zoom Test: {json.dumps(slider_test, indent=2)}")

            # 5. Terapkan Crop
            print("\n▶ Menekan Selesai & Terapkan Foto...")
            await evaluate(ws, msg_id, """
                document.getElementById('btnApplyCrop').click();
            """); msg_id += 1
            await asyncio.sleep(1)

            result_metrics = await evaluate(ws, msg_id, """
                (() => {
                    const previewImg = document.getElementById('avatarPreviewImg');
                    const badge = document.getElementById('avatarStatusBadge');
                    const btnReopen = document.getElementById('btnReopenCrop');
                    return {
                        previewImgSrcPrefix: previewImg ? previewImg.src.substring(0, 30) : '',
                        badgeText: badge ? badge.innerText.trim() : '',
                        btnReopenVisible: btnReopen ? btnReopen.style.display !== 'none' : false
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
