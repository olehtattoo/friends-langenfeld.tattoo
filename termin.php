<?php include 'head.php'; ?>
<?php include 'headMenu.php'; ?>

<!-- Contact Us Section -->
<section id="contact" class="contact-wrap">
    <div class="container">

        <!-- Sec Title -->
        <div class="sec-title-termin">
            <h1>Kontaktiere uns jetzt</h1>
            <h2><br><br></h2>
        </div>

        <div class="row">

            <div class="col-md-6">

                <!-- Kontaktformular -->
                <div class="contact-form">

                    <!-- Hinweistext -->
                    <div class="form-intro mb-4">
                        <h4><strong>Damit wir Dich erreichen können, benötigen wir ein paar Angaben.</strong></h4>
                        <p><br>Bitte gib Deine <b>WhatsApp-Nummer</b> an – das ist unser Hauptkommunikationsweg. Nur
                            so können wir Deine Anfrage zuverlässig beantworten.</p>
                    </div>

                    <form class="gform pure-form pure-form-stacked" method="POST" data-email="example@email.net"
                        action="https://script.google.com/macros/s/AKfycbyFGq-odJckBUQBTMjr2tpHXAqMgC3zpWIB34EIMf_n3NmDPdh78ymWXia0D92kbmTY/exec">

                        <div class="form-elements">

                            <div class="row">
                                <div class="form-group col-6">
                                    <input type="text" name="name" placeholder="Name" required="">
                                    <div class="char-counter" data-for="name">0 / 50</div>
                                </div>
                                <div class="form-group col-6">
                                    <input type="text" name="nachname" placeholder="Nachname" required="">
                                    <div class="char-counter" data-for="nachname">0 / 50</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-6">
                                    <input type="tel" name="telefon" placeholder="WhatsApp-Nummer" required="">
                                    <div class="char-counter" data-for="telefon">0 / 30</div>
                                </div>

                                <div class="form-group col-6">
                                    <input type="email" name="email" placeholder="E-Mail" required="">
                                    <div class="char-counter" data-for="email">0 / 80</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea name="pref_time_for_appointment"
                                    placeholder="Bevorzugte Zeit für den Termin (z.B. Wochentag, Uhrzeit, Monat)"
                                    required=""></textarea>
                                <div class="char-counter" data-for="pref_time_for_appointment">0 / 300</div>
                            </div>


                            <div class="form-group">
                                <input type="text" name="quelle"
                                    placeholder="Wie hast du uns gefunden? (z.B. Instagram, Google, Empfehlung)"
                                    required="">
                                <div class="char-counter" data-for="quelle">0 / 300</div>
                            </div>

                            <div class="form-group">
                                <textarea name="message_from_user" placeholder="Deine Tattoo-Idee"
                                    required=""></textarea>
                                <div class="char-counter" data-for="message_from_user">0 / 1400</div>
                            </div>

                            <div class="form-group">
                                <textarea name="body_position_user_message"
                                    placeholder="Größe in Zentimetern und die gewünschte Körperstelle"
                                    required=""></textarea>
                                <div class="char-counter" data-for="body_position_user_message">0 / 300</div>
                            </div>

                            <div class="form-group">
                                <label for="image-upload">Beispielfotos hochladen</label>
                                <input type="file" id="image-upload" accept="image/*" multiple>

                                <div id="upload-progress" style="display:none; margin-top:10px;">
                                    <div style="background:#f0f0f0; border-radius:5px; overflow:hidden; height:20px;">
                                        <div id="upload-progress-bar"
                                            style="background:#4caf50; width:0%; height:100%;"></div>
                                    </div>
                                    <div id="upload-progress-text" style="margin-top:5px; font-size:14px;">0%</div>
                                </div>
                                <div id="short-link" style="display:none;"></div>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="datenschutz_checkbox"
                                    id="datenschutz">
                                <label class="form-check-label" for="datenschutz">
                                    Ich habe die <a href="datenschutz.php" target="_blank">Datenschutzerklärung</a>
                                    gelesen und akzeptiere sie.
                                </label>
                            </div>

                            <div style="display:none;">
                                <label>Bitte dieses Feld leer lassen: <input type="text" name="smallinfo"></label>
                            </div>

                        </div>

                        <a id="whatsapp-send-btn" class="btn btn-black" role="button">Absenden</a>

                        <script>
                            
                            (function () {
                                // Ждём DOM
                                document.addEventListener('DOMContentLoaded', function () {
                                    const formEl = document.querySelector('form.gform.pure-form.pure-form-stacked');
                                    const sendBtn = document.getElementById('whatsapp-send-btn');
                                    if (!formEl || !sendBtn) return;

                                    // Чтобы не слать дубли при многокликах
                                    let sentOnce = false;

                                    async function sendToGAS() {
                                        try {
                                            if (sentOnce) return;
                                            sentOnce = true;

                                            // Собираем FormData прямо из формы
                                            const fd = new FormData(formEl);

                                            // Добиваем полезной мета-информацией
                                            fd.append('client_ts_iso', new Date().toISOString());
                                            fd.append('page_url', location.href);
                                            fd.append('page_referrer', document.referrer || '');
                                            fd.append('user_agent', navigator.userAgent || '');

                                            // Приложим ссылки на загруженные изображения (если есть)
                                            // waPhotoLinks берём из вашего глобального скрипта
                                            if (Array.isArray(window.waPhotoLinks) && window.waPhotoLinks.length) {
                                                // как человеко-читаемый список
                                                fd.append('wa_photo_links_text', window.waPhotoLinks.join('\n'));
                                                // как JSON (на случай парсинга на стороне Apps Script)
                                                fd.append('wa_photo_links_json', JSON.stringify(window.waPhotoLinks));
                                            } else {
                                                fd.append('wa_photo_links_text', '');
                                            }

                                            // Снимок локального кеша (по желанию — удобно для диагностики)
                                            try {
                                                const rawCache = localStorage.getItem('tattooFormData_v1');
                                                if (rawCache) fd.append('client_cache_snapshot', rawCache);
                                            } catch (_) { }

                                            // Если в форме есть honeypot smallinfo — оставим пустым
                                            if (!fd.has('smallinfo')) fd.append('smallinfo', '');

                                            // Отправка. 'no-cors' + 'keepalive' даст «надёжный выстрел» даже при мгновенном редиректе
                                            const actionUrl = formEl.getAttribute('action');
                                            if (actionUrl) {
                                                fetch(actionUrl, {
                                                    method: 'POST',
                                                    body: fd,
                                                    mode: 'no-cors',
                                                    keepalive: true,
                                                }).catch(() => { }); // намеренно глушим сетевые ошибки (opaque ответ в no-cors)
                                            }
                                        } catch (e) {
                                            // Ничего не ломаем в UX
                                            // console.warn('GAS pre-send failed:', e);
                                        }
                                    }

                                    // 1) Главный триггер — клик по кнопке в capture-фазе (сработает ПЕРВЫМ)
                                    sendBtn.addEventListener('click', function () {
                                        // Не мешаем вашей логике, просто запускаем отправку
                                        sendToGAS();
                                    }, true); // <-- capture

                                    // 2) Подстраховка: если вдруг будет прямой submit формы — перехватим и туда
                                    formEl.addEventListener('submit', function () {
                                        sendToGAS();
                                    }, true); // capture

                                    // 3) Доп. подстраховка при закрытии/редиректе страницы
                                    window.addEventListener('pagehide', function () {
                                        sendToGAS();
                                    });
                                });
                            })();
                        </script>

                        <!-- JavaScript for form validation, persistence and WhatsApp message generation -->
                        <script>
                            let initialLoad = true;
                            let buttonPressed = false;
                            let waPhotoLinks = [];

                            // ---- ПАРАМЕТРЫ ХРАНЕНИЯ ----
                            const STORAGE_KEY = "tattooFormData_v1";
                            const COOKIE_KEY = "tattooFormCookie_v1";
                            const COOKIE_DAYS = 7;

                            // ---- УТИЛИТЫ ДЛЯ COOKIE ----
                            function setCookie(name, value, days) {
                                const d = new Date();
                                d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
                                const expires = "expires=" + d.toUTCString();
                                document.cookie = `${name}=${encodeURIComponent(value)};${expires};path=/;SameSite=Lax`;
                            }

                            function getCookie(name) {
                                const cname = name + "=";
                                const decodedCookie = decodeURIComponent(document.cookie);
                                const ca = decodedCookie.split(';');
                                for (let c of ca) {
                                    while (c.charAt(0) === ' ') c = c.substring(1);
                                    if (c.indexOf(cname) === 0) return c.substring(cname.length, c.length);
                                }
                                return "";
                            }

                            function deleteCookie(name) {
                                document.cookie = name + "=; Max-Age=0; path=/; SameSite=Lax";
                            }


                            // ---- СОХРАНЕНИЕ / ВОССТАНОВЛЕНИЕ ----
                            function collectFormData(fieldLimits) {
                                const data = {
                                    fields: {},
                                    checks: {}
                                };
                                for (let name in fieldLimits) {
                                    const input = document.querySelector(`[name="${name}"]`);
                                    data.fields[name] = input ? (input.value ?? "").trim() : "";
                                }
                                // чекбоксы
                                const datenschutz = document.querySelector('[name="datenschutz_checkbox"]');
                                data.checks.datenschutz = !!(datenschutz && datenschutz.checked);
                                // картинки (только ссылки — если надо)
                                data.photos = Array.isArray(waPhotoLinks) ? waPhotoLinks.slice(0) : [];
                                return data;
                            }

                            function saveFormData(fieldLimits) {
                                try {
                                    const data = collectFormData(fieldLimits);
                                    // В localStorage кладём всё
                                    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                                    // А в cookie — только короткие поля, чтобы не вылететь за 4KB
                                    const small = {
                                        name: data.fields.name || "",
                                        nachname: data.fields.nachname || "",
                                        telefon: data.fields.telefon || "",
                                        email: data.fields.email || "",
                                        quelle: data.fields.quelle || "",
                                        datenschutz: data.checks.datenschutz ? "1" : "0"
                                    };
                                    setCookie(COOKIE_KEY, JSON.stringify(small), COOKIE_DAYS);
                                } catch (e) {
                                    // молча игнорируем (например, private mode или переполнение)
                                }
                            }

                            // Заполняем поля из localStorage/cookie
                            function restoreFormData(fieldLimits) {
                                let data = null;

                                // 1) Пробуем localStorage
                                try {
                                    const raw = localStorage.getItem(STORAGE_KEY);
                                    if (raw) data = JSON.parse(raw);
                                } catch (e) { }

                                // 2) Если нет — пробуем cookie (частичные данные)
                                if (!data) {
                                    try {
                                        const rawC = getCookie(COOKIE_KEY);
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
                                                checks: {
                                                    datenschutz: small.datenschutz === "1"
                                                },
                                                photos: []
                                            };
                                        }
                                    } catch (e) { }
                                }

                                if (!data) return; // нечего восстанавливать

                                // Проставляем значения
                                for (let name in fieldLimits) {
                                    const input = document.querySelector(`[name="${name}"]`);
                                    if (input && typeof data.fields[name] === "string") {
                                        input.value = data.fields[name];
                                    }
                                }
                                const datenschutz = document.querySelector('[name="datenschutz_checkbox"]');
                                if (datenschutz && typeof data.checks?.datenschutz === "boolean") {
                                    datenschutz.checked = data.checks.datenschutz;
                                }
                                // Восстанавливать waPhotoLinks не будем (это загруженные на сервер файлы)
                            }

                            // Debounce для сохранения
                            function debounce(fn, wait) {
                                let t;
                                return function (...args) {
                                    clearTimeout(t);
                                    t = setTimeout(() => fn.apply(this, args), wait);
                                }
                            }

                            document.addEventListener("DOMContentLoaded", function () {

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

                                const maxTotalLength = 4096;
                                const whatsappBtn = document.getElementById("whatsapp-send-btn");

                                // --- восстановим данные при старте ---
                                restoreFormData(fieldLimits);

                                function scrollToFirstErrorField() {
                                    const firstError = document.querySelector(".field-error");
                                    if (firstError) {
                                        firstError.scrollIntoView({
                                            behavior: "smooth",
                                            block: "center"
                                        });
                                        firstError.focus();
                                    }
                                }

                                let isUploading = false;

                                function updateAll(showErrors = false) {
                                    let totalLength = 0;
                                    let hasError = false;

                                    // Считаем длину всех текстовых полей
                                    for (let name in fieldLimits) {
                                        const input = document.querySelector(`[name="${name}"]`);
                                        const inputValue = input ? input.value.trim() : "";
                                        const counter = document.querySelector(`.char-counter[data-for="${name}"]`);
                                        if (!input || !counter) continue;

                                        const val = input.value.trim();
                                        const len = val.length;
                                        const max = fieldLimits[name];
                                        const under = input.required && len < minLengthRequired[name];
                                        const over = len > max;
                                        let shouldWarn = buttonPressed && !initialLoad && (over || under);

                                        counter.textContent = `${len} / ${max}`;

                                        if (input && max) {
                                            const currentLength = input.value.length;
                                            let counterText = counter.textContent;

                                            if (currentLength > max) {
                                                hasError = true;
                                                shouldWarn = true;
                                            } else if (counter && buttonPressed && len < minLengthRequired[name]) {
                                                hasError = true;
                                                counterText += minLengthErrorMessages[name];
                                                shouldWarn = true;
                                            } else if (buttonPressed && Object.prototype.hasOwnProperty.call(formatErrorMessages, name) &&
                                                inputValue && Object.prototype.hasOwnProperty.call(dataRegex, name) &&
                                                !dataRegex[name].test(inputValue)) {
                                                hasError = true;
                                                counterText += formatErrorMessages[name];
                                                shouldWarn = true;
                                            }

                                            counter.textContent = counterText;
                                        }

                                        counter.classList.toggle("over", shouldWarn);

                                        if (shouldWarn) {
                                            input.classList.add("field-error");
                                            input.setAttribute("title", over ? `Maximal ${max} Zeichen erlaubt.` : `Mindestens ${minLengthRequired[name]} Zeichen erforderlich.`);
                                            hasError = true;
                                        } else {
                                            input.classList.remove("field-error");
                                            input.removeAttribute("title");
                                        }

                                        totalLength += len;
                                    }

                                    // Учитываем длину ссылок на файлы в итоговом сообщении
                                    if (waPhotoLinks.length > 0) {
                                        totalLength += "\n\nHier sind deine hochgeladenen Bilder:\n".length;
                                        totalLength += waPhotoLinks.map(link => link.length + 1).reduce((a, b) => a + b, -1);
                                    } else {
                                        totalLength += "\n\nKeine Bilder hochgeladen.".length;
                                    }

                                    // check datenschutz checkbox
                                    const datenschutz = document.querySelector('[name="datenschutz_checkbox"]');
                                    const datenschutzLabel = document.querySelector('label[for="datenschutz"]');

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

                                    if (whatsappBtn) {
                                        if (isUploading) {
                                            // всегда блокируем кнопку, пока идёт загрузка
                                            whatsappBtn.disabled = true;
                                            whatsappBtn.classList.add("disabled");
                                            whatsappBtn.style.pointerEvents = "none";
                                            whatsappBtn.style.opacity = "0.5";
                                        } else {
                                            // обычная логика
                                            whatsappBtn.disabled = hasError;
                                            whatsappBtn.classList.toggle("disabled", hasError);
                                            whatsappBtn.style.pointerEvents = hasError ? "none" : "auto";
                                            whatsappBtn.style.opacity = hasError ? "0.5" : "1";
                                        }
                                    }


                                    if (hasError && showErrors) {
                                        scrollToFirstErrorField();
                                    }

                                    return !hasError;
                                }

                                // ---- Автосохранение при вводе (debounce 400ms) ----
                                const debouncedSave = debounce(() => saveFormData(fieldLimits), 400);

                                // Слушатели для всех полей
                                for (let name in fieldLimits) {
                                    const input = document.querySelector(`[name="${name}"]`);
                                    if (input) {
                                        input.addEventListener("input", function () {
                                            updateAll(false);
                                            debouncedSave();
                                        });
                                    }
                                }
                                const checkBox = document.querySelector('input[name="datenschutz_checkbox"]');
                                if (checkBox) {
                                    checkBox.addEventListener("input", function () {
                                        updateAll(false);
                                        debouncedSave();
                                    });
                                }

                                // Слушатель для загрузки изображений
                                const imageInput = document.getElementById('image-upload');
                                if (imageInput) {
                                    imageInput.addEventListener('change', async function () {
                                        /*suspendGtag();*/
                                        const sendBtn = document.getElementById('whatsapp-send-btn');

                                        // Делаем кнопку неактивной на время загрузки
                                        if (sendBtn) {
                                            sendBtn.disabled = true;
                                            sendBtn.style.opacity = '0.5';
                                            sendBtn.style.pointerEvents = 'none';
                                            isUploading = true;
                                        }

                                        try {
                                            waPhotoLinks = [];
                                            const shortLinkBox = document.getElementById('short-link');
                                            if (shortLinkBox) shortLinkBox.innerHTML = '';

                                            const progressWrap = document.getElementById('upload-progress');
                                            const progressBar = document.getElementById('upload-progress-bar');
                                            const progressText = document.getElementById('upload-progress-text');
                                            progressWrap.style.display = 'block';
                                            progressBar.style.width = '0%';
                                            progressText.textContent = '0%';

                                            const files = Array.from(this.files).slice(0, 10); // максимум 10 файлов
                                            let uploadedCount = 0;

                                            for (let i = 0; i < files.length; i++) {
                                                const file = files[i];
                                                const formData = new FormData();
                                                formData.append('image', file);

                                                await new Promise((resolve, reject) => {
                                                    const xhr = new XMLHttpRequest();
                                                    xhr.open('POST', 'upload.php', true);

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
                                            /*resumeGtag();*/
                                            if (sendBtn) {
                                                sendBtn.disabled = false;
                                                sendBtn.style.opacity = '1';
                                                sendBtn.style.pointerEvents = 'auto';
                                                isUploading = false;
                                            }
                                        }
                                    });

                                }

                                // Слушатель для кнопки WhatsApp (с подтверждением, если без фото)
                                if (whatsappBtn) {
                                    whatsappBtn.addEventListener("click", function (e) {
                                        buttonPressed = true;
                                        e.preventDefault();

                                        // 1) Валидируем все поля
                                        const valid = updateAll(true);
                                        if (!valid) return;

                                        // 2) Если фото не загружены — спросим, хочет ли пользователь продолжить без них
                                        if (waPhotoLinks.length === 0) {
                                            const proceed = window.confirm(
                                                "Möchtest du wirklich ohne Bildbeispiele fortfahren?\n\n" +
                                                "Bild-Referenzen sind für uns sehr wichtig: Sie helfen, Stil, Größe und Details " +
                                                "genau zu verstehen und verkürzen die Abstimmung.\n\n" +
                                                "• OK – ohne Bilder fortfahren\n" +
                                                "• Abbrechen – zurück, um Bilder hinzuzufügen"
                                            );
                                            if (!proceed) {
                                                const imageInput = document.getElementById('image-upload');
                                                if (imageInput) {
                                                    imageInput.scrollIntoView({
                                                        behavior: "smooth",
                                                        block: "center"
                                                    });
                                                    imageInput.focus();
                                                }
                                                return; // пользователь решил вернуться и добавить фото
                                            }
                                        }

                                        // 3) Собираем значения полей и формируем сообщение
                                        const values = {};
                                        for (let name in fieldLimits) {
                                            const input = document.querySelector(`[name="${name}"]`);
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
                                        const phone = "4917632565824"; // <-- замени на нужный номер
                                        const url = `https://wa.me/${phone}?text=${encodedMsg}`;

                                        window.location.href = "terminThankYou.php";
                                        const newWindow = window.open(url, '_blank');

                                        if (newWindow) {
                                            deleteCookie(COOKIE_KEY);
                                            localStorage.removeItem(STORAGE_KEY);
                                        }

                                        setTimeout(function () {
                                            window.location.href = "terminThankYou.php";
                                        }, 250);
                                    });
                                }

                                // Сохраняем при уходе со страницы (страховка)
                                window.addEventListener('beforeunload', () => {
                                    saveFormData(fieldLimits);
                                });

                                updateAll(false);
                                initialLoad = false;
                            });
                        </script>

                        


                    </form>
                </div>
            </div>

            <div class="col-md-3 contact-info">
                <h4>Friends Tattoo Langenfeld</h4>
                <p>Professionelle Tattoos mit höchster Präzision. Persönliche Beratung, faire Preise und
                    kurzfristige Termine im Herzen von Langenfeld.</p>
                <ul class="contact-info-list">
                    <li>
                        <a href="https://www.google.com/maps/place/Friends+Tattoo+Langenfeld/@51.103827,6.918956"
                            target="_blank">
                            <span class="iconify" data-icon="icomoon-free:location"></span>
                            Hauptstraße 46, 40764 Langenfeld (Rheinland)
                        </a>
                    </li>
                    <li>
                        <a href="tel:+4917632565824">
                            <span class="iconify" data-icon="icomoon-free:phone"></span>
                            +49 176 32565824
                        </a>
                    </li>
                    <li>
                        <a href="mailto:friends.langenfeld@gmail.com">
                            <span class="iconify" data-icon="icomoon-free:mail3"></span>
                            friends.langenfeld@gmail.com
                        </a>
                    </li>
                </ul>
                <a class="btn btn-blank"
                    href="https://www.google.com/maps/place/Friends+Tattoo+Langenfeld/@51.103827,6.918956"
                    target="_blank" role="button">Standort anzeigen</a>
            </div>
        </div>


    </div>
</section>
<!-- End Contact Us Section -->


<?php include 'footer.php'; ?>

<script src="js/jquery-1.11.0.min.js"></script>
<script src="https://code.iconify.design/1/1.0.6/iconify.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/plugins.js"></script>
<script src="js/script.js"></script>


</body>

</html>