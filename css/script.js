document.addEventListener('DOMContentLoaded', function() {
  const toggleLink = document.getElementById('toggleFormLink');
  const newThreadForm = document.getElementById('newThreadForm');
  let formVisible = false;

  toggleLink.addEventListener('click', function(e) {
    e.preventDefault();
    formVisible = !formVisible;

    if (formVisible) {
      newThreadForm.style.display = 'block';
      toggleLink.textContent = '[X]';
    } else {
      newThreadForm.style.display = 'none';
      toggleLink.textContent = '[NEW]';
    }
  });
});
