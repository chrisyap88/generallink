/**
 * GeneralLink — Malaysian Address Auto-Lookup
 * Include this script once in dashboard.blade.php
 * Usage: add class="postcode-input" to any postcode field
 *        add data-city="city_field_id" and data-state="state_field_id"
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── POSTCODE AUTO-LOOKUP ──────────────────────────────────────────────
    document.querySelectorAll('.postcode-input').forEach(function (input) {
        var dropdown = document.createElement('div');
        dropdown.style.cssText = 'display:none;position:absolute;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:999;max-height:200px;overflow-y:auto;width:100%;';
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(dropdown);

        var lookupTimeout;

        input.addEventListener('input', function () {
            var val = this.value.trim();
            clearTimeout(lookupTimeout);
            dropdown.style.display = 'none';

            if (val.length < 3) return;

            lookupTimeout = setTimeout(function () {
                fetch('/admin/postcode-lookup?postcode=' + encodeURIComponent(val) + '&partial=1')
                    .then(function(r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.length === 0) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var div = document.createElement('div');
                            div.style.cssText = 'padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;';
                            div.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ', <span style="color:#6b7280;">' + item.state + '</span>';
                            div.addEventListener('mouseenter', function () { this.style.background = '#f0f9ff'; });
                            div.addEventListener('mouseleave', function () { this.style.background = '#fff'; });
                            div.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                input.value = item.postcode;
                                dropdown.style.display = 'none';
                                fillCityState(input, item.city, item.state);
                            });
                            dropdown.appendChild(div);
                        });
                        dropdown.style.display = 'block';
                    })
                    .catch(function() { dropdown.style.display = 'none'; });
            }, 300);
        });

        input.addEventListener('blur', function () {
            setTimeout(function () { dropdown.style.display = 'none'; }, 200);
        });

        // If exactly 5 digits, auto-fill immediately
        input.addEventListener('change', function () {
            if (this.value.trim().length === 5) {
                fetch('/admin/postcode-lookup?postcode=' + encodeURIComponent(this.value.trim()))
                    .then(function(r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.city) fillCityState(input, data.city, data.state);
                    });
            }
        });
    });

    function fillCityState(postcodeInput, city, state) {
        // Find city and state fields via data attributes or naming convention
        var cityId    = postcodeInput.getAttribute('data-city');
        var stateId   = postcodeInput.getAttribute('data-state');
        var cityField  = cityId  ? document.getElementById(cityId)  : null;
        var stateField = stateId ? document.getElementById(stateId) : null;

        // Fallback: look for fields with matching name patterns nearby
        if (!cityField) {
            var form = postcodeInput.closest('form');
            if (form) {
                var nameBase = postcodeInput.name.replace('postcode', '').replace('_postcode', '');
                cityField  = form.querySelector('[name="' + nameBase + 'city"]') ||
                             form.querySelector('[name="' + nameBase + '_city"]') ||
                             form.querySelector('[name="city"]');
                stateField = form.querySelector('[name="' + nameBase + 'state"]') ||
                             form.querySelector('[name="' + nameBase + '_state"]') ||
                             form.querySelector('[name="state"]') ||
                             form.querySelector('[name="vendor_state"]');
            }
        }

        if (cityField)  { cityField.value = city; cityField.style.background = '#f0fdf4'; setTimeout(function() { cityField.style.background = ''; }, 1500); }
        if (stateField) {
            if (stateField.tagName === 'SELECT') {
                for (var i = 0; i < stateField.options.length; i++) {
                    if (stateField.options[i].value === state) { stateField.selectedIndex = i; break; }
                }
            } else {
                stateField.value = state;
            }
            stateField.style.background = '#f0fdf4';
            setTimeout(function() { stateField.style.background = ''; }, 1500);
        }
    }

    // ── CITY AUTOCOMPLETE ─────────────────────────────────────────────────
    document.querySelectorAll('.city-input').forEach(function (input) {
        var dropdown = document.createElement('div');
        dropdown.style.cssText = 'display:none;position:absolute;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:999;max-height:200px;overflow-y:auto;width:100%;';
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(dropdown);

        var cityTimeout;

        input.addEventListener('input', function () {
            var val = this.value.trim();
            clearTimeout(cityTimeout);
            dropdown.style.display = 'none';
            if (val.length < 2) return;

            cityTimeout = setTimeout(function () {
                fetch('/admin/postcode-lookup?city=' + encodeURIComponent(val))
                    .then(function(r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.length === 0) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        var seen = {};
                        data.forEach(function (item) {
                            if (seen[item.city]) return;
                            seen[item.city] = true;
                            var div = document.createElement('div');
                            div.style.cssText = 'padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;';
                            div.innerHTML = item.city + ', <span style="color:#6b7280;">' + item.state + '</span>';
                            div.addEventListener('mouseenter', function () { this.style.background = '#f0f9ff'; });
                            div.addEventListener('mouseleave', function () { this.style.background = '#fff'; });
                            div.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                input.value = item.city;
                                dropdown.style.display = 'none';
                                // Auto-fill state
                                var form = input.closest('form');
                                if (form) {
                                    var stateField = form.querySelector('select[name*="state"]') || form.querySelector('[name*="state"]');
                                    if (stateField && stateField.tagName === 'SELECT') {
                                        for (var i = 0; i < stateField.options.length; i++) {
                                            if (stateField.options[i].value === item.state) { stateField.selectedIndex = i; break; }
                                        }
                                    }
                                }
                            });
                            dropdown.appendChild(div);
                        });
                        dropdown.style.display = 'block';
                    })
                    .catch(function() { dropdown.style.display = 'none'; });
            }, 300);
        });

        input.addEventListener('blur', function () {
            setTimeout(function () { dropdown.style.display = 'none'; }, 200);
        });
    });

});
