(function () {
    "use strict";

    const pathName = window.location.pathname.toLowerCase();
    const inServiceFolder = pathName.indexOf("/diensten-/") !== -1;
    const prefix = inServiceFolder ? "../" : "./";
    const siteHeader = document.getElementById("site-header");
    const siteFooter = document.getElementById("site-footer");
    const logoSlider = document.getElementById("site-logo-slider");
    const newsList = document.getElementById("news-list");

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function nl2br(value) {
        return escapeHtml(value).replace(/\n/g, "<br>");
    }

    function renderHeader(siteData) {
        if (!siteHeader) {
            return;
        }

        const banner = siteData.banner || {};
        const bannerHtml = banner.enabled && banner.text
            ? "<div class=\"site-banner\">" + nl2br(banner.text) + "</div>"
            : "";

        siteHeader.innerHTML =
            "<nav>" +
            "  <div class=\"logo\">" +
            "    <img src=\"" + prefix + "VEA logo.jpg\" height=\"60\" alt=\"Van Exel Automatisering\">" +
            "  </div>" +
            "  <ul>" +
            "    <li><a href=\"" + prefix + "homepage.html\">Home</a></li>" +
            "    <li><a href=\"" + prefix + "diensten.html\">Diensten</a></li>" +
            "    <li><a href=\"" + prefix + "support.php\">Support</a></li>" +
            "    <li><a href=\"" + prefix + "nieuws.html\">Nieuws</a></li>" +
            "    <li><a href=\"" + prefix + "contact.html\">Contact</a></li>" +
            "  </ul>" +
            "</nav>" +
            bannerHtml;
    }

    function renderFooter(siteData) {
        if (!siteFooter) {
            return;
        }

        const year = Number(siteData.copyrightYear) || new Date().getFullYear();

        siteFooter.innerHTML =
            "<footer>" +
            "  <div class=\"footer-grid\">" +
            "    <div>" +
            "      <h4>Contact</h4>" +
            "      <p>Stationsplein 123<br>Nijkerk<br>033-1234567</p>" +
            "    </div>" +
            "    <div>" +
            "      <h4>Support</h4>" +
            "      <p><a href=\"" + prefix + "algemenevoorwaarde.html\">Algemene voorwaarden</a><br><a href=\"#\">TeamViewer Hulp</a></p>" +
            "    </div>" +
            "  </div>" +
            "  <div class=\"footer-bottom\">&copy; " + year + " Van Exel Automatisering</div>" +
            "</footer>";
    }

    function renderLogoSlider(siteData) {
        if (!logoSlider) {
            return;
        }

        const logos = Array.isArray(siteData.logos) ? siteData.logos : [];

        if (!logos.length) {
            logoSlider.innerHTML = "";
            return;
        }

        let logosHtml = "";
        for (let i = 0; i < 2; i += 1) {
            for (let j = 0; j < logos.length; j += 1) {
                const logo = logos[j];
                logosHtml +=
                    "<img src=\"" + prefix + "afbeeldingen/" + escapeHtml(logo.file || "") + "\" alt=\"" + escapeHtml(logo.name || "") + "\">";
            }
        }

        logoSlider.innerHTML =
            "<section class=\"merken\">" +
            "  <div class=\"logo-slider\">" +
            "    <div class=\"logo-track\">" + logosHtml + "</div>" +
            "  </div>" +
            "</section>";
    }

    function renderNews(items) {
        if (!newsList) {
            return;
        }

        if (!items.length) {
            newsList.innerHTML = "<p>Er zijn nog geen nieuwsberichten.</p>";
            return;
        }

        let html = "";
        for (let i = 0; i < items.length; i += 1) {
            const item = items[i];
            const image = item.image
                ? "<img src=\"" + prefix + "uploads/nieuws/" + escapeHtml(item.image) + "\" alt=\"\">"
                : "";

            html +=
                "<div class=\"service-card news-card\">" +
                image +
                "<div class=\"news-content\">" +
                "<h3>" + escapeHtml(item.titel || "") + "</h3>" +
                "<small>" + escapeHtml(item.datum || "") + "</small>" +
                "<p>" + nl2br(item.tekst || "") + "</p>" +
                "</div>" +
                "</div>";
        }

        newsList.innerHTML = html;
    }

    async function loadSiteData() {
        const requests = await Promise.all([
            fetch(prefix + "data/banner.json"),
            fetch(prefix + "data/merken.json")
        ]);

        const bannerResponse = requests[0];
        const logosResponse = requests[1];

        if (!bannerResponse.ok || !logosResponse.ok) {
            throw new Error("Kon site-data niet laden.");
        }

        const bannerData = await bannerResponse.json();
        const logosData = await logosResponse.json();
        const bannerText = bannerData && bannerData.text ? String(bannerData.text).trim() : "";
        const enabled = !!(bannerData && bannerData.enabled && bannerText !== "");
        const logos = Array.isArray(logosData)
            ? logosData
                  .filter(function (logo) {
                      return logo && logo.bestand;
                  })
                  .map(function (logo) {
                      return {
                          name: logo.naam || "",
                          file: logo.bestand || ""
                      };
                  })
            : [];

        return {
            banner: {
                enabled: enabled,
                text: bannerText
            },
            logos: logos,
            copyrightYear: new Date().getFullYear()
        };
    }

    async function loadNews() {
        if (!newsList) {
            return;
        }

        const response = await fetch(prefix + "data/nieuws.json");
        if (!response.ok) {
            throw new Error("Kon nieuws niet laden.");
        }
        const data = await response.json();
        renderNews(Array.isArray(data) ? data : []);
    }

    document.addEventListener("DOMContentLoaded", async function () {
        try {
            const siteData = await loadSiteData();
            renderHeader(siteData);
            renderLogoSlider(siteData);
            renderFooter(siteData);
        } catch (error) {
            console.error(error);
        }

        try {
            await loadNews();
        } catch (error) {
            console.error(error);
            if (newsList) {
                newsList.innerHTML = "<p>Nieuws kon niet worden geladen.</p>";
            }
        }

    });
})();
