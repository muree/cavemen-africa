const registrationForm = document.querySelector("[data-tatsuniya-registration-form]");
const registrationStatus = document.querySelector("[data-registration-status]");

if (registrationForm && registrationStatus) {
  function setStatus(message) {
    registrationStatus.textContent = message;
    registrationStatus.classList.add("is-visible");
  }

  registrationForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(registrationForm);

    const childName = String(formData.get("childName") || "").trim();
    const childAge = String(formData.get("childAge") || "").trim();
    const childSchool = String(formData.get("childSchool") || "").trim();
    const parentName = String(formData.get("parentName") || "").trim();
    const phone = String(formData.get("phone") || "").trim();
    const email = String(formData.get("email") || "").trim();
    const discovery = String(formData.get("discovery") || "").trim();
    const medical = String(formData.get("medical") || "").trim();
    const notes = String(formData.get("notes") || "").trim();

    if (!childName || !childAge || !parentName || !phone || !email || !discovery) {
      setStatus("Please complete all required fields before submitting.");
      return;
    }

    const age = Number(childAge);
    if (age < 8 || age > 15) {
      setStatus("Tatsuniya is open to children between 8 and 15 years old.");
      return;
    }

    if (!email.includes("@")) {
      setStatus("Please enter a valid parent or guardian email address.");
      return;
    }

    const submitButton = registrationForm.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Processing...";
    }

    try {
      const response = await fetch(window.cavemenApiEndpoint("tatsuniya-registrations"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          childName,
          childAge,
          childSchool,
          parentName,
          phone,
          email,
          discovery,
          medical,
          notes,
        }),
      });

      let payload;
      try {
        payload = await response.json();
      } catch {
        throw new Error("Invalid server response.");
      }

      if (!response.ok) {
        throw new Error(payload.error || "Registration could not be completed.");
      }

      registrationForm.reset();

      if (payload.paymentUrl) {
        setStatus(
          `Registration received. Redirecting you to Flutterwave to complete your payment of ₦${payload.ticketPriceNaira}.`,
        );
        window.location.href = payload.paymentUrl;
        return;
      }

      setStatus("Registration received. The Cavemen Africa team will follow up with you soon.");
    } catch (error) {
      console.error("Tatsuniya registration error:", error);
      setStatus(
        error && error.message
          ? error.message
          : "We could not complete your registration right now. Please try again in a moment.",
      );
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = "Register now";
      }
    }
  });
}
