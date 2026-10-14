window.addEventListener("beforeunload", function () {
    sessionStorage.setItem("scrollPos", window.scrollY);
});

window.addEventListener("load", function () {
    const scrollPos = sessionStorage.getItem("scrollPos");

    if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos, 10));
    }
});

window.addEventListener("load", function () {
    const scrollPos = sessionStorage.getItem("scrollPos");

    if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos, 10));
        sessionStorage.removeItem("scrollPos");
    }
});