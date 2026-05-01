const registrationForm = document.querySelector("[data-dahk-registration-form]");
const registrationStatus = document.querySelector("[data-registration-status]");

if (registrationForm && registrationStatus) {
  function setStatus(message) {
    registrationStatus.textContent = message;
    registrationStatus.classList.add("is-visible");
  }

  registrationForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(registrationForm);
    const fullName = String(formData.get("fullName") || "").trim();
    const phone = String(formData.get("phone") || "").trim();
    const email = String(formData.get("email") || "").trim();
    const gender = String(formData.get("gender") || "").trim();
    const discovery = String(formData.get("discovery") || "").trim();
    const attendanceType = String(formData.get("attendanceType") || "").trim();
    const notes = String(formData.get("notes") || "").trim();

    if (!fullName || !phone || !email || !gender || !discovery || !attendanceType) {
      setStatus("Please complete all required fields before submitting.");
      return;
    }

    try {
      const response = await fetch(window.cavemenApiEndpoint("dahk-registrations"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          fullName,
          phone,
          email,
          gender,
          discovery,
          attendanceType,
          notes,
        }),
      });

      let payload;
      try {
        payload = await response.json();
      } catch {
        setStatus(
          "The registration server returned an unexpected response. Open `/cavemen-api.php?route=health` on your site — if that fails, upload `site/cavemen-api.php`. For local dev run `php -S localhost:8080 router.php` from the site folder.",
        );
        return;
      }

      if (!response.ok) {
        setStatus(payload.error || "Your registration could not be submitted right now.");
        return;
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
      console.error(error);
      setStatus("The server could not be reached. Please try again in a moment.");
    }
  });
}
