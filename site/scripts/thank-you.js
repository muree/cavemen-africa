(function () {
  const params = new URLSearchParams(window.location.search);
  const txRef =
    params.get("tx_ref") ||
    params.get("txRef") ||
    params.get("cavemen_tx_ref") ||
    params.get("ref");

  const statusRoot = document.querySelector("[data-thankyou-status]");
  const downloadRow = document.querySelector("[data-thankyou-download]");
  const downloadLink = document.querySelector("[data-ticket-pdf-link]");
  const pollNote = document.querySelector("[data-thankyou-poll-note]");

  if (!txRef) {
    if (statusRoot) {
      statusRoot.textContent =
        "We could not read your payment reference in the URL. Check your email for your ticket, or contact info@cavemen.africa.";
    }
    if (downloadRow) {
      downloadRow.hidden = true;
    }
    if (pollNote) {
      pollNote.hidden = true;
    }
    return;
  }

  if (statusRoot) {
    statusRoot.textContent = "Confirming your payment and ticket…";
  }
  if (downloadRow) {
    downloadRow.hidden = true;
  }
  if (pollNote) {
    pollNote.hidden = false;
  }

  const maxAttempts = 45;
  let attempt = 0;

  function showReady(payload) {
    if (statusRoot) {
      statusRoot.textContent = `Thanks for your payment, ${payload.fullName || "friend"}. Your ticket is ready.`;
    }
    if (downloadRow) {
      downloadRow.hidden = false;
    }
    if (downloadLink) {
      downloadLink.href = payload.pdfUrl || `/api/asali-ticket.pdf?tx_ref=${encodeURIComponent(txRef)}`;
    }
    if (pollNote) {
      pollNote.hidden = true;
    }
  }

  function showPending() {
    if (statusRoot) {
      statusRoot.textContent =
        "Your payment is still being confirmed. You can use Download below in a few seconds, or check your email for the same ticket.";
    }
    if (downloadRow) {
      downloadRow.hidden = false;
    }
    if (downloadLink) {
      downloadLink.href = `/api/asali-ticket.pdf?tx_ref=${encodeURIComponent(txRef)}`;
    }
    if (pollNote) {
      pollNote.textContent = "If download fails, wait a few seconds and try again.";
    }
  }

  async function poll() {
    try {
      const res = await fetch(
        `/api/asali-payment-status?tx_ref=${encodeURIComponent(txRef)}`,
        { cache: "no-store" },
      );
      if (!res.ok) {
        throw new Error(String(res.status));
      }
      const data = await res.json();
      if (data.ticketReady && data.pdfUrl) {
        showReady(data);
        return;
      }
      if (data.status === "paid" && !data.ticketCode) {
        showPending();
        return;
      }
      attempt += 1;
      if (attempt < maxAttempts) {
        window.setTimeout(poll, 2000);
      } else {
        if (statusRoot) {
          statusRoot.textContent =
            "We could not confirm the ticket in time. You will still receive the ticket by email, or use Download to try the PDF (after payment is confirmed).";
        }
        showPending();
        if (pollNote) {
          pollNote.textContent = "";
        }
      }
    } catch (e) {
      console.error(e);
      if (statusRoot) {
        statusRoot.textContent =
          "We could not reach the server to confirm your ticket. Check your email, or try refreshing this page.";
      }
      showPending();
    }
  }

  void poll();
})();
