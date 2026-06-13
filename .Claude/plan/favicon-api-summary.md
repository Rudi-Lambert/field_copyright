# Favicon API Summary
## For implementation of a dual-provider favicon fetching module

---

## Two Providers

### 1. Google

**Public endpoint (shorthand):**
```
https://www.google.com/s2/favicons?domain=<domain>&sz=<size>
```

**What actually happens:** The `s2/favicons` URL is a redirect layer. It chains through to the real endpoint on Google's CDN:
```
https://t[n].gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=http://<domain>&size=<size>
```
The `t[n]` is a load-balanced CDN node (t0, t1, t2, t3…). You can call `faviconV2` directly if you want to skip the redirect.

**Key parameters on faviconV2:**
- `client=SOCIAL` — identifies the use context
- `type=FAVICON` — asset type requested
- `fallback_opts=TYPE,SIZE,URL` — instructs the service to fall back through type → size → URL if no perfect match
- `url=http://<domain>` — the target domain (include the `http://` prefix)
- `size=<n>` — requested size in pixels (16, 32, 64, 128…); Google picks the closest available

**Size support:** Yes. The `sz` param on the `s2/favicons` shorthand and `size` on `faviconV2` both work. Google will upscale/pick the nearest available size.

**Behaviour when no icon exists:** Returns a **404**. The browser fires `onerror` on an `<img>` tag. This makes fallback detection straightforward.

**CORS:** Does not send `Access-Control-Allow-Origin`, so canvas-based pixel inspection is blocked client-side (tainted canvas). Stick to status code / `onerror` detection.

---

### 2. DuckDuckGo

**Endpoint:**
```
https://icons.duckduckgo.com/ip3/<domain>.ico
```

No query parameters. The `.ico` extension is part of the route, not a file type guarantee — the response is often a PNG regardless.

**Size support:** None. Returns a single icon at whatever size DuckDuckGo has cached (typically 16–32px). Display size can be controlled with CSS but the underlying bitmap is fixed.

**Behaviour when no icon exists:** Returns **200 OK with a generic placeholder image**. The browser considers the image successfully loaded; `onerror` does NOT fire. This makes fallback detection harder.

**Detection options when no real icon exists:**
1. **Dimension check on load** — inspect `img.naturalWidth` / `img.naturalHeight` after the `load` event and treat a known-placeholder size as "no real icon found." Fragile (real icons can share that size) but zero-dependency.
2. **Server-side proxy** — fetch the icon on the backend, hash or compare byte-length against the known placeholder, serve your own fallback if it matches. Most reliable, sidesteps all CORS issues.

**CORS:** Same limitation as Google — canvas pixel inspection is blocked client-side.

---

## Comparison Table

| Feature | Google | DuckDuckGo |
|---|---|---|
| Size parameter | Yes (`sz` / `size`) | No |
| Fallback on missing icon | 404 → `onerror` fires | 200 with placeholder |
| `onerror` detection works | ✅ Yes | ❌ No |
| Redirect chain | Yes (s2 → gstatic CDN) | Simple (one hop) |
| Avoids Google dependency | ❌ No | ✅ Yes |
| Best for programmatic fallback | ✅ Easier | ❌ Harder |

---

## Recommended Implementation Approach

**If clean fallback control matters:** use Google. A missing icon gives a genuine 404 and `onerror` handles it cleanly on the client with no extra logic.

**If avoiding Google is the priority:** use DuckDuckGo, but handle fallback server-side (proxy the request, compare against the known placeholder) rather than trying to detect it in the browser.

**Suggested module interface:**
```js
getFavicon(domain, {
  provider: 'google' | 'duckduckgo',  // default: 'google'
  size: 32,                            // ignored for duckduckgo
  fallbackUrl: '/my-fallback.svg'      // shown if no icon found
})
```

For Google, fallback detection = `onerror` on the `<img>`.
For DuckDuckGo, fallback detection = dimension check client-side, or a server-side proxy that compares the response against the known placeholder bytes/dimensions.

---

## Privacy Note

Both services receive the domain name you query, meaning they can observe which sites your users are looking up favicons for. If that's a concern, a self-hosted proxy (fetch → cache → serve) eliminates the third-party exposure for both providers.

---

## Alternative Services (for reference)

- **Clearbit Logo API:** `https://logo.clearbit.com/<domain>?size=<n>` — supports sizing, returns higher-res logos rather than strict favicons, coverage varies.
- **Self-hosted:** Fetch the page, parse `<link rel="icon">` tags from `<head>` (priority order matters), resolve the URL, handle ICO/PNG/SVG/WebP. Robust but fiddly across the real web.
