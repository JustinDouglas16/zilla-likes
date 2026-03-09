(() => {
  "use strict";

  const config = window.ZillaLikesConfig || {};
  const { restUrl, nonce } = config;

  if (!restUrl) {
    return;
  }

  /**
   * @param {string} endpoint
   * @param {RequestInit} options
   * @returns {Promise<any>}
   */
  async function api(endpoint, options = {}) {
    const headers = {
      "Content-Type": "application/json",
    };

    if (nonce) {
      headers["X-WP-Nonce"] = nonce;
    }

    const response = await fetch(`${restUrl}${endpoint}`, {
      credentials: "same-origin",
      ...options,
      headers: {
        ...headers,
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      throw new Error(`ZillaLikes API error: ${response.status}`);
    }

    return response.json();
  }

  /**
   * Update a button's DOM to reflect server state.
   *
   * @param {HTMLElement} button
   * @param {{ count: number, liked: boolean, postfix: string }} state
   */
  function updateButton(button, state) {
    const countEl = button.querySelector(".zilla-likes-count");
    const postfixEl = button.querySelector(".zilla-likes-postfix");

    if (countEl) countEl.textContent = String(state.count);
    if (postfixEl) postfixEl.textContent = state.postfix;

    button.classList.toggle("is-liked", state.liked);
    button.title = state.liked
      ? button.dataset.titleLiked || "You already like this"
      : button.dataset.titleDefault || "Like this";
    button.setAttribute("aria-label", button.title);
  }

  /**
   * Batch-fetch like states for all buttons on the page.
   */
  async function hydrate() {
    /** @type {NodeListOf<HTMLElement>} */
    const buttons = document.querySelectorAll(".zilla-likes[data-post-id]");

    if (!buttons.length) return;

    const ids = [...new Set([...buttons].map((b) => b.dataset.postId))];

    try {
      const states = await api(`/likes?ids=${ids.join(",")}`);

      buttons.forEach((button) => {
        const id = button.dataset.postId;
        if (states[id]) {
          updateButton(button, states[id]);
        }
      });
    } catch (err) {
      console.error("ZillaLikes hydration failed:", err);
    }
  }

  /**
   * Handle a like button click.
   *
   * @param {MouseEvent} event
   */
  async function handleClick(event) {
    const button = event.target.closest(".zilla-likes[data-post-id]");
    if (!button || button.classList.contains("is-loading")) return;

    event.preventDefault();

    const postId = button.dataset.postId;

    // Optimistic update
    const wasLiked = button.classList.contains("is-liked");
    const countEl = button.querySelector(".zilla-likes-count");
    const currentCount = countEl ? parseInt(countEl.textContent, 10) || 0 : 0;
    const optimisticCount = wasLiked
      ? Math.max(0, currentCount - 1)
      : currentCount + 1;

    button.classList.toggle("is-liked", !wasLiked);
    if (countEl) countEl.textContent = String(optimisticCount);
    button.classList.add("is-loading");

    try {
      const state = await api(`/like/${postId}`, { method: "POST" });
      updateButton(button, state);
    } catch (err) {
      console.error("ZillaLikes toggle failed:", err);
      // Revert optimistic update
      button.classList.toggle("is-liked", wasLiked);
      if (countEl) countEl.textContent = String(currentCount);
    } finally {
      button.classList.remove("is-loading");
    }
  }

  // Use event delegation on body – works for dynamic content too
  document.addEventListener("click", handleClick);

  // Hydrate on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", hydrate);
  } else {
    hydrate();
  }
})();
