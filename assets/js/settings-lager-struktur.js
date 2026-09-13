(function () {
  const locationSelect = document.getElementById('dg_shelf_location');
  const hallSelect = document.getElementById('dg_shelf_hall');
  if (!locationSelect || !hallSelect) {
    return;
  }

  const allHallOptions = Array.from(hallSelect.querySelectorAll('option[data-location-id]')).map(function (opt) {
    return {
      value: opt.value,
      label: opt.textContent,
      locationId: opt.getAttribute('data-location-id'),
    };
  });

  function filterHalls() {
    const locationId = locationSelect.value;
    const selectedHall = hallSelect.value;
    hallSelect.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '— wählen —';
    hallSelect.appendChild(empty);

    allHallOptions.forEach(function (opt) {
      if (locationId && opt.locationId !== locationId) {
        return;
      }
      const option = document.createElement('option');
      option.value = opt.value;
      option.textContent = opt.label;
      option.setAttribute('data-location-id', opt.locationId);
      if (opt.value === selectedHall) {
        option.selected = true;
      }
      hallSelect.appendChild(option);
    });
  }

  locationSelect.addEventListener('change', filterHalls);
  filterHalls();
})();
