(function () {
  const form = document.querySelector("[data-kanti-checkout-form]") || document.getElementById("kanti-checkout");
  const statusRoot = document.querySelector("[data-checkout-status]");
  if (!form) {
    return;
  }

  function setStatus(message) {
    if (!statusRoot) {
      return;
    }
    statusRoot.textContent = message;
    statusRoot.hidden = false;
    statusRoot.classList.add("is-visible");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const fullName = String(formData.get("fullName") || "").trim();
    const phone = String(formData.get("phone") || "").trim();
    const email = String(formData.get("email") || "").trim();
    const address = String(formData.get("address") || "").trim();
    const city = String(formData.get("city") || "").trim();
    const state = String(formData.get("state") || "").trim();
    const notes = String(formData.get("notes") || "").trim();
    const productType = String(formData.get("product_type") || formData.get("productType") || "").trim();
    const selectedScent = String(formData.get("selected_scent") || formData.get("selectedScent") || "").trim();
    const quantity = Number(formData.get("quantity") || 1);

    if (!fullName || !phone || !email || !address || !city || !state || !productType) {
      setStatus("Please complete all required fields before submitting.");
      return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const response = await fetch(window.cavemenApiEndpoint("kanti-orders"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          fullName,
          phone,
          email,
          address,
          city,
          state,
          notes,
          productType,
          selectedScent,
          quantity,
        }),
      });

      let payload;
      try {
        payload = await response.json();
      } catch {
        throw new Error("The checkout server returned an unexpected response.");
      }

      if (!response.ok) {
        throw new Error(payload.error || "Your order could not be submitted right now.");
      }

      if (payload.paymentUrl) {
        setStatus(
          `Order received. Redirecting you to Flutterwave to complete your payment of ₦${payload.totalNaira || payload.ticketPriceNaira}.`,
        );
        window.location.href = payload.paymentUrl;
        return;
      }

      setStatus("Order received. The Cavemen Africa team will follow up with you soon.");
    } catch (error) {
      console.error(error);
      setStatus(error && error.message ? error.message : "The server could not be reached. Please try again in a moment.");
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
})();
