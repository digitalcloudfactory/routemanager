<!-- THEME TOGGLE SCRIPT -->
<script>
  const toggle = document.getElementById("themeToggle");
  const root = document.documentElement;

  // Load saved theme
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) {
    root.setAttribute("data-theme", savedTheme);
    toggle.textContent = savedTheme === "dark"
      ? "☀️ Light mode"
      : "🌙 Dark mode";
  }

  toggle.addEventListener("click", () => {
    const current = root.getAttribute("data-theme") || "light";
    const next = current === "light" ? "dark" : "light";

    root.setAttribute("data-theme", next);
    localStorage.setItem("theme", next);

    toggle.textContent = next === "dark"
      ? "☀️ Light mode"
      : "🌙 Dark mode";
  });
</script>
</body>
</html>
