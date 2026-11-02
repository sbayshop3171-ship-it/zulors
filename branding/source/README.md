# Branding source images

- `logo-black.png` is used for the light theme logo.
- `logo-white.png` is used for the dark theme logo and the media watermark.
- `favicon.jpg` is the original source; `favicon-upload.png` is its 512×512 PNG version for the admin uploader.
- The favicon artwork is used for the 32×32 site favicon, the 180×180 Apple touch icon, and the 192×192 and 512×512 PWA icons.

The admin favicon upload is cropped to a square and saved at all four favicon and PWA sizes. PWA icon URLs use content hashes so browser and CDN caches refresh when the favicon changes.
