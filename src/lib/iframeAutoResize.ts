// Optional nicety for the "iframe embed" WordPress option: reports this
// page's content height to its parent window so the host page's iframe
// can resize to fit without scrollbars. No-ops when not inside an iframe.
// See README "iframe embed" section for the matching parent-side snippet.
export function initIframeAutoResize() {
  if (typeof window === "undefined" || window.self === window.top) return;

  const postHeight = () => {
    const height = document.documentElement.scrollHeight;
    window.parent.postMessage({ type: "instant-quote-widget:resize", height }, "*");
  };

  postHeight();
  const observer = new ResizeObserver(postHeight);
  observer.observe(document.documentElement);
  window.addEventListener("load", postHeight);
}
