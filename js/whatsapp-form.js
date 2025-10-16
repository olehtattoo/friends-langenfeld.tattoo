// whatsapp-form.js
(() => {
  const DEFAULTS = {
    phone: '',                 // '49176...'
    thankYouUrl: 'thankyou.html',
    storageKey: 'tattooFormData_v1',
    cookieKey: 'tattooFormCookie_v1',
    cookieDays: 7,
    maxFiles: 10,
    uploadEndpoint: 'upload.php',
    debug: false
  };

  function debounce(fn, wait) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  // ---- cookie utils ----
  function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = "expires=" + d.toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)};${expires};path=/;SameSite=Lax`;
  }
  function getCookie(name) {
    const cname = name + "=";
    const decoded = decodeURIComponent(document.cookie);
    const parts = decoded.split(';');
    for (let c of parts) {
      while (c.charAt(0) === ' ') c = c.substring(1);
      if (c.indexOf(cname) === 0) return c.substring(cname.length);
    }
    return "";
  }
  function deleteCookie(name) {
    document.cookie = `${name}=; Max-Age=0; path=/; SameSite=Lax`;
  }

  // Экспорт
  window.initWhatsAppForm = function initWhatsAppForm(formEl, sendBtn, opts = {}) {
    if (!formEl || !sendBtn) return;
    const cfg = Object.assign({}, DEFAULTS, opts);
    const log = (...a) => cfg.debug && console.log('[WA]', ...a);

    // ===== Конфигурация полей и валидации =====
    const fieldLimits = {
      name: 50,
      nachname: 50,
      telefon: 30,
      email: 80,
      message_from_user: 1400,
      body_position_user_message: 300,
      pref_time_for_appointment: 300,
      quelle: 300
    };
    const minLengthRequired = {
      name: 2,
      nachname: 2,
      telefon: 5,
      email: 5,
      message_from_user: 15,
      body_position_user_message: 5,
      pref_time_for_appointment: 10,
      quelle: 0
    };
    const minLengthErrorMessages = {
      name: ` Bitte gib mindestens ${minLengthRequired.name} Zeichen für den Vornamen ein.`,
      nachname: ` Bitte gib mindestens ${minLengthRequired.nachname} Zeichen für den Nachnamen ein.`,
      telefon: ` Bitte gib eine gültige Telefonnummer mit mindestens ${minLengthRequired.telefon} Zeichen ein.`,
      email: ` Bitte gib eine gültige E-Mail-Adresse mit mindestens ${minLengthRequired.email} Zeichen ein.`,
      message_from_user: ` Bitte beschreibe dein Tattoo-Vorhaben in mindestens ${minLengthRequired.message_from_user} Zeichen.`,
      body_position_user_message: ` Bitte gib an, wo auf dem Körper das Tattoo platziert werden soll (mind. ${minLengthRequired.body_position_user_message} Zeichen).`,
      pref_time_for_appointment: ` Bitte teile uns deinen bevorzugten Zeitraum mit (mind. ${minLengthRequired.pref_time_for_appointment} Zeichen).`,
      quelle: ` Bitte teile uns mit, wie du uns gefunden hast (optional).`
    };
    const formatErrorMessages = {
      email: " Bitte gib eine gültige E-Mail-Adresse im richtigen Format ein (z. B. name@example.com).",
      telefon: " Bitte gib eine gültige Telefonnummer ein (nur Zahlen, +, -, Leerzeichen erlaubt, mind. 5 Zeichen)."
    };
    const dataRegex = {
      email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
      telefon: /^[+0-9\s\-()]{5,}$/
    };

    // ===== Состояние =====
    let buttonPressed = false;
    let initialLoad = true;
    let isUploading = false;
    let waPhotoLinks = [];

    // ===== Сохранение/восстановление =====
    function collectFormData() {
      const data = { fields: {}, checks: {}, photos: Array.isArray(waPhotoLinks) ? waPhotoLinks.slice(0) : [] };
      for (const name in fieldLimits) {
        const input = formEl.querySelector(`[name="${name}"]`);
        data.fields[name] = input ? (input.value ?? '').trim() : '';
      }
      const datenschutz = formEl.querySelector('[name="datenschutz_checkbox"]');
      data.checks.datenschutz = !!(datenschutz && datenschutz.checked);
      return data;
    }
    function saveFormData() {
      try {
        const data = collectFormData();
        localStorage.setItem(cfg.storageKey, JSON.stringify(data));
        const small = {
          name: data.fields.name || "",
          nachname: data.fields.nachname || "",
          telefon: data.fields.telefon || "",
          email: data.fields.email || "",
          quelle: data.fields.quelle || "",
          datenschutz: data.checks.datenschutz ? "1" : "0"
        };
        setCookie(cfg.cookieKey, JSON.stringify(small), cfg.cookieDays);
      } catch { /* ignore */ }
    }
    const debouncedSave = debounce(saveFormData, 400);

    function restoreFormData() {
      let data = null;
      try {
        const raw = localStorage.getItem(cfg.storageKey);
        if (raw) data = JSON.parse(raw);
      } catch {}
      if (!data) {
        try {
          const rawC = getCookie(cfg.cookieKey);
          if (rawC) {
            const small = JSON.parse(rawC);
            data = {
              fields: {
                name: small.name || "",
                nachname: small.nachname || "",
                telefon: small.telefon || "",
                email: small.email || "",
                message_from_user: "",
                body_position_user_message: "",
                pref_time_for_appointment: "",
                quelle: small.quelle || ""
              },
              checks: { datenschutz: small.datenschutz === "1" },
              photos: []
            };
          }
        } catch {}
      }
      if (!data) return;

      for (const name in fieldLimits) {
        const input = formEl.querySelector(`[name="${name}"]`);
        if (input && typeof data.fields[name] === 'string') input.value = data.fields[name];
      }
      const datenschutz = formEl.querySelector('[name="datenschutz_checkbox"]');
      if (datenschutz && typeof data.checks?.datenschutz === 'boolean') {
        datenschutz.checked = data.checks.datenschutz;
      }
    }

    // ===== UI helpers =====
    function scrollToFirstErrorField() {
      const firstError = formEl.querySelector(".field-error");
      if (firstError) {
        firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        firstError.focus();
      }
    }

    function updateAll(showErrors = false) {
      let hasError = false;

      for (const name in fieldLimits) {
        const input = formEl.querySelector(`[name="${name}"]`);
        const counter = formEl.querySelector(`.char-counter[data-for="${name}"]`);
        if (!input || !counter) continue;

        const val = input.value.trim();
        const len = val.length;
        const max = fieldLimits[name];
        const under = input.required && len < minLengthRequired[name];
        const over = len > max;
        let shouldWarn = buttonPressed && !initialLoad && (over || under);

        let text = `${len} / ${max}`;
        if (over) {
          hasError = true;
          shouldWarn = true;
        } else if (buttonPressed && len < minLengthRequired[name]) {
          hasError = true;
          text += minLengthErrorMessages[name];
          shouldWarn = true;
        } else if (buttonPressed &&
                   Object.prototype.hasOwnProperty.call(formatErrorMessages, name) &&
                   val &&
                   Object.prototype.hasOwnProperty.call(dataRegex, name) &&
                   !dataRegex[name].test(val)) {
          hasError = true;
          text += formatErrorMessages[name];
          shouldWarn = true;
        }

        counter.textContent = text;
        counter.classList.toggle('over', shouldWarn);

        if (shouldWarn) {
          input.classList.add('field-error');
          input.setAttribute('title', over ? `Maximal ${max} Zeichen erlaubt.` : `Mindestens ${minLengthRequired[name]} Zeichen erforderlich.`);
        } else {
          input.classList.remove('field-error');
          input.removeAttribute('title');
        }
      }

      // Check Datenschutzerklärung
      const datenschutz = formEl.querySelector('[name="datenschutz_checkbox"]');
      const datenschutzLabel = formEl.querySelector('label[for="datenschutz"]');
      if (!initialLoad && buttonPressed) {
        if (datenschutz && !datenschutz.checked) {
          hasError = true;
          if (datenschutzLabel) {
            datenschutzLabel.classList.add("field-error");
            datenschutzLabel.setAttribute("title", "Bitte akzeptiere die Datenschutzerklärung.");
          }
        } else if (datenschutzLabel) {
          datenschutzLabel.classList.remove("field-error");
          datenschutzLabel.removeAttribute("title");
        }
      }

      // Кнопка состояния
      if (isUploading) {
        sendBtn.disabled = true;
        sendBtn.classList.add("disabled");
        sendBtn.style.pointerEvents = "none";
        sendBtn.style.opacity = "0.5";
      } else {
        sendBtn.disabled = hasError;
        sendBtn.classList.toggle("disabled", hasError);
        sendBtn.style.pointerEvents = hasError ? "none" : "auto";
        sendBtn.style.opacity = hasError ? "0.5" : "1";
      }

      if (hasError && showErrors) scrollToFirstErrorField();
      return !hasError;
    }

    // ===== Слушатели инпутов =====
    for (const name in fieldLimits) {
      const input = formEl.querySelector(`[name="${name}"]`);
      if (input) {
        input.addEventListener('input', () => {
          updateAll(false);
          debouncedSave();
        });
      }
    }
    const checkBox = formEl.querySelector('input[name="datenschutz_checkbox"]');
    if (checkBox) {
      checkBox.addEventListener('input', () => {
        updateAll(false);
        debouncedSave();
      });
    }

    // ===== Загрузка изображений с прогрессом =====
    const imageInput = formEl.querySelector('#image-upload');
    const shortLinkBox = formEl.querySelector('#short-link');
    const progressWrap = formEl.querySelector('#upload-progress');
    const progressBar = formEl.querySelector('#upload-progress-bar');
    const progressText = formEl.querySelector('#upload-progress-text');

    if (imageInput && progressWrap && progressBar && progressText) {
      imageInput.addEventListener('change', async function () {
        isUploading = true;
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.5';
        sendBtn.style.pointerEvents = 'none';

        try {
          waPhotoLinks = [];
          if (shortLinkBox) shortLinkBox.innerHTML = '';
          progressWrap.style.display = 'block';
          progressBar.style.width = '0%';
          progressText.textContent = '0%';

          const files = Array.from(this.files).slice(0, cfg.maxFiles);
          let uploadedCount = 0;

          for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const formData = new FormData();
            formData.append('image', file);

            await new Promise((resolve, reject) => {
              const xhr = new XMLHttpRequest();
              xhr.open('POST', cfg.uploadEndpoint, true);

              xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                  const totalUploaded = ((uploadedCount * 100) + (e.loaded / e.total) * 100) / files.length;
                  progressBar.style.width = totalUploaded.toFixed(0) + '%';
                  progressText.textContent = totalUploaded.toFixed(0) + '%';
                }
              });

              xhr.onload = function () {
                if (xhr.status === 200) {
                  try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.url) {
                      waPhotoLinks.push(`Bild Nummer ${i + 1}: ${data.url}`);
                      if (shortLinkBox) {
                        const a = document.createElement('a');
                        a.href = data.url;
                        a.target = '_blank';
                        a.textContent = data.url;
                        shortLinkBox.appendChild(a);
                        shortLinkBox.appendChild(document.createElement('br'));
                      }
                      uploadedCount++;
                      resolve();
                    } else {
                      alert('Fehler beim Upload');
                      reject();
                    }
                  } catch {
                    alert('Fehler beim Upload');
                    reject();
                  }
                } else {
                  alert('Fehler beim Upload');
                  reject();
                }
              };

              xhr.onerror = () => {
                alert('Fehler beim Upload');
                reject();
              };

              xhr.send(formData);
            });
          }

          progressBar.style.width = '100%';
          progressText.textContent = '100%';
          updateAll(false);
          debouncedSave();
        } finally {
          sendBtn.disabled = false;
          sendBtn.style.opacity = '1';
          sendBtn.style.pointerEvents = 'auto';
          isUploading = false;
        }
      });
    }

    // ===== Клик по кнопке WhatsApp =====
    sendBtn.addEventListener('click', (e) => {
      buttonPressed = true;
      e.preventDefault();

      // 1) валидация
      const valid = updateAll(true);
      if (!valid) return;

      // 2) подтверждение без фото
      if (waPhotoLinks.length === 0) {
        const proceed = window.confirm(
          "Möchtest du wirklich ohne Bildbeispiele fortfahren?\n\n" +
          "Bild-Referenzen sind für uns sehr wichtig: Sie helfen, Stil, Größe und Details " +
          "genau zu verstehen und verkürzen die Abstimmung.\n\n" +
          "• OK – ohne Bilder fortfahren\n" +
          "• Abbrechen – zurück, um Bilder hinzuzufügen"
        );
        if (!proceed) {
          const imageInput = formEl.querySelector('#image-upload');
          if (imageInput) {
            imageInput.scrollIntoView({ behavior: "smooth", block: "center" });
            imageInput.focus();
          }
          return;
        }
      }

      // 3) собрать значения
      const values = {};
      for (const name in fieldLimits) {
        const input = formEl.querySelector(`[name="${name}"]`);
        values[name] = input ? input.value.trim() : "";
      }

      let message =
        `Tattoo-Anfrage:\n\n` +
        `Name: ${values.name} ${values.nachname}\n` +
        `Telefon (WhatsApp): ${values.telefon}\n` +
        `E-Mail: ${values.email}\n\n` +
        `Tattoo-Idee: ${values.message_from_user}\n` +
        `Größe / Stelle: ${values.body_position_user_message}\n\n` +
        `Bevorzugte Zeit für Termin: ${values.pref_time_for_appointment}\n` +
        `Gefunden über: ${values.quelle}`;

      if (waPhotoLinks.length > 0) {
        message += `\n\nHier sind deine hochgeladenen Bilder:\n${waPhotoLinks.join('\n')}`;
      } else {
        message += `\n\nKeine Bilder hochgeladen.`;
      }

      const encodedMsg = encodeURIComponent(message);
      const url = `https://wa.me/${cfg.phone}?text=${encodedMsg}`;

      // 4) переходы
      window.location.href = cfg.thankYouUrl;
      const w = window.open(url, '_blank');

      if (w) {
        deleteCookie(cfg.cookieKey);
        localStorage.removeItem(cfg.storageKey);
      }

      setTimeout(() => { window.location.href = cfg.thankYouUrl; }, 250);
    });

    // страховка — сохранить перед уходом
    window.addEventListener('beforeunload', () => {
      saveFormData();
    });

    // старт
    restoreFormData();
    updateAll(false);
    initialLoad = false;
    log('initialized');
  };
})();
