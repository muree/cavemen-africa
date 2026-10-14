(function () {
  const params = new URLSearchParams(window.location.search);
  const txRef =
    params.get("tx_ref") ||
    params.get("txRef") ||
    params.get("cavemen_tx_ref") ||
    params.get("ref");

  const statusRoot = document.querySelector("[data-thankyou-status]");
  const pollNote = document.querySelector("[data-thankyou-poll-note]");
  const route = document.body.getAttribute("data-checkout-status-route") || "asali-payment-status";
  const paidNoun = document.body.getAttribute("data-checkout-paid-label") || "payment";

  if (!txRef) {
    if (statusRoot) {
      statusRoot.textContent =
        "We could not read your payment reference in the URL. Check your email, or contact info@cavemen.africa.";
    }
    if (pollNote) {
      pollNote.hidden = true;
    }
    return;
  }

  if (statusRoot) {
    statusRoot.textContent = "Confirming your payment…";
  }
  if (pollNote) {
    pollNote.hidden = false;
  }

  const maxAttempts = 45;
  let attempt = 0;

  function showReady(payload) {
    const name = payload.fullName || "friend";
    if (statusRoot) {
      statusRoot.textContent = `Thanks for your payment, ${name}. Your ${paidNoun} is confirmed.`;
    }
    if (pollNote) {
      pollNote.hidden = true;
    }
  }

  function showPending() {
    if (statusRoot) {
      statusRoot.textContent =
        "Your payment is still being confirmed. You can wait a few seconds, or check the email you used at checkout.";
    }
  }

  async function poll() {
    try {
      const res = await fetch(window.cavemenApiEndpoint(route, { tx_ref: txRef }), { cache: "no-store" });
      if (!res.ok) {
        throw new Error(String(res.status));
      }
      const data = await res.json();
      if (data.status === "paid" || data.ticketReady) {
        showReady(data);
        return;
      }
      attempt += 1;
      if (attempt < maxAttempts) {
        window.setTimeout(poll, 2000);
      } else {
        showPending();
        if (pollNote) {
          pollNote.textContent = "If this page does not update, check your email or write to info@cavemen.africa.";
        }
      }
    } catch (e) {
      console.error(e);
      if (statusRoot) {
        statusRoot.textContent =
          "We could not reach the server to confirm your payment. Check your email, or try refreshing this page.";
      }
    }
  }

  void poll();
})();
