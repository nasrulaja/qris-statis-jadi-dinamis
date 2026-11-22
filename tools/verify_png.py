#!/usr/bin/env python3
"""Opsional: pastikan PNG hasil generate benar-benar bisa dipindai.

Memakai OpenCV (pip install opencv-python-headless).
Pemakaian: python3 tools/verify_png.py qris-25000.png ["string yang diharapkan"]
Juga mengecek CRC16 string hasil scan.
"""
import binascii
import sys

import cv2


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 2

    img = cv2.imread(sys.argv[1])
    if img is None:
        print("Gagal membaca gambar:", sys.argv[1])
        return 1

    text, _, _ = cv2.QRCodeDetector().detectAndDecode(img)
    if not text:
        print("QR tidak terbaca.")
        return 1

    print("Hasil scan:", text)
    crc_ok = "%04X" % binascii.crc_hqx(text[:-4].encode(), 0xFFFF) == text[-4:].upper()
    print("CRC16 valid:", crc_ok)
    ok = crc_ok
    if len(sys.argv) > 2:
        same = text == sys.argv[2]
        print("Sama dengan yang diharapkan:", same)
        ok = ok and same
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
