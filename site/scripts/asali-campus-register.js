const registrationForm = document.querySelector("[data-asali-campus-registration-form]");
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
    const notes = String(formData.get("notes") || "").trim();

    if (!fullName || !phone || !email || !gender || !discovery) {
      setStatus("Please complete all required fields before submitting.");
      return;
    }

    try {
      const response = await fetch(window.cavemenApiEndpoint("asali-campus-registrations"), {
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
          notes,
        }),
      });

      let payload;
      try {
        payload = await response.json();
      } catch {
        setStatus(
          "The registration server returned an unexpected response. If testing locally, run `php -S localhost:8080 router.php` from the repository root.",
        );
        return;
      }

      if (!response.ok) {
        setStatus(payload.error || "Your registration could not be submitted right now.");
        return;
      }

      registrationForm.reset();
      setStatus("Registration successful. We look forward to seeing you at the Asali Campus Tour!");
    } catch (error) {
      console.error(error);
      setStatus("The server could not be reached. Please try again in a moment.");
    }
  });
}
