 // Εμφάνιση / Απόκρυψη burger menu
  const menuToggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');

  menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
  });

  document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.getElementById('nav-links');

    if(menuToggle && navLinks){
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            menuToggle.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
        });
    }

    const cartBtn = document.querySelector('.cart-btn');
    const cartDropdown = document.getElementById('cartDropdown');

    if(cartBtn && cartDropdown){
        cartBtn.addEventListener('click', () => {
            cartDropdown.classList.toggle('show');
        });
    }
});
