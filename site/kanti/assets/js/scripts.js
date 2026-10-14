/**
 * DAHK Seasons — Interactive Scent Table
 */

document.addEventListener("DOMContentLoaded", function () {

    const scentTabs = document.querySelectorAll(".scent-step");
    const spotlightImg = document.getElementById("spotlight-scent-img");
    const spotlightNum = document.getElementById("spotlight-scent-num");
    const spotlightName = document.getElementById("spotlight-scent-name");
    const spotlightNotes = document.getElementById("spotlight-scent-notes");
    const spotlightDesc = document.getElementById("spotlight-scent-desc");
    const spotlightVibe = document.getElementById("spotlight-scent-vibe");
    const spotlightBuyBtn = document.getElementById("spotlight-buy-btn");

    scentTabs.forEach(tab => {

        tab.addEventListener("click", function () {

            // Remove active state
            scentTabs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");

            // Get values from button
           
            const id = this.getAttribute("data-id");
            const num = this.getAttribute("data-num");
            const name = this.getAttribute("data-name");
            const notes = this.getAttribute("data-notes");
            const desc = this.getAttribute("data-desc");
            const vibe = this.getAttribute("data-vibe");
             const img = this.getAttribute("data-img");

            // Update text content
            spotlightNum.textContent = "Season 0" + num;
            spotlightName.textContent = name;
            spotlightNotes.textContent = "Aromatic Formulation: " + notes;
            spotlightDesc.textContent = desc;
            spotlightVibe.textContent = "Vibe: " + vibe;

            // Update image
            if (spotlightImg && img) {
                spotlightImg.src = img;
            }

            // Update button
            if (spotlightBuyBtn) {
                spotlightBuyBtn.href =
                    "../purchase/?type=single-vial&scent=" + id;

                spotlightBuyBtn.textContent =
                    "Buy Single Bottled Scent: " + name;
            }
        });
    });

    document.querySelectorAll(".season-tab:not(.scent-step)").forEach(function (tab) {
        tab.addEventListener("click", function () {
            document.querySelectorAll(".season-tab:not(.scent-step)").forEach(function (item) {
                item.classList.remove("active");
            });
            this.classList.add("active");
            const season = document.getElementById("excerpt-season");
            const text = document.getElementById("excerpt-text");
            if (season) {
                season.textContent = "Season " + this.getAttribute("data-num") + " — " + this.getAttribute("data-name") + " Excerpt";
            }
            if (text) {
                text.textContent = "\u201c" + this.getAttribute("data-excerpt") + "\u201d";
            }
        });
    });

    const quantity = document.getElementById("order-quantity");
    const unitPrice = document.getElementById("unit-price");
    const subtotal = document.getElementById("order-subtotal");
    const grandTotal = document.getElementById("order-grand-total");
    if (quantity && unitPrice && grandTotal) {
        const formatNaira = function (amount) {
            return "\u20a6" + Number(amount).toLocaleString("en-NG");
        };
        const updateTotal = function () {
            const count = Math.min(25, Math.max(1, parseInt(quantity.value, 10) || 1));
            quantity.value = String(count);
            const total = count * (parseInt(unitPrice.value, 10) || 0);
            if (subtotal) subtotal.textContent = formatNaira(total);
            grandTotal.textContent = formatNaira(total);
        };
        quantity.addEventListener("input", updateTotal);
        updateTotal();
    }
});