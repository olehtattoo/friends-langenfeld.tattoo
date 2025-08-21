<body data-bs-spy="scroll" data-bs-target="#navbar" data-bs-root-margin="0px 0px -40%" data-bs-smooth-scroll="true"
    tabindex="0">

    <div id="preloader">
        <div id="loader"></div>
    </div>

    <header class="header-wrap">

        <div class="header-logo">
            <a class="site-logo" href="index.php">
                <img src="images/logo.png" alt="logo">
            </a>
        </div>

        <nav class="header-nav-wrap">

            <?php

            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
            $basename = basename($path);
            $isIndex = ($basename === '' || $basename === 'index.php');
            $isTermin = ($basename === 'termin.php');
            $isFaq = ($basename === 'faq_and_about_me.php');

            $hrefs = [
                'home' => $isIndex ? '#home' : 'index.php#home',
                'about' => $isIndex ? '#about' : 'index.php#about',
                'gallery' => $isIndex ? '#gallery' : 'index.php#gallery',

                'termin' => $isTermin ? '#contact' : 'termin.php#contact',

                'aboutme' => $isFaq ? '#aboutme' : 'faq_and_about_me.php#aboutme',
                'faq' => $isFaq ? '#faq' : 'faq_and_about_me.php#faq',
            ];

            $activeStart = ' active';

            ?>

            <ul id="navbar" class="header-main-nav">
                <li><a href="<?= htmlspecialchars($hrefs['home']) ?>" class="nav-link<?= $activeStart ?>">Startseite</a>
                </li>
                <li><a href="<?= htmlspecialchars($hrefs['about']) ?>" class="nav-link">Rezensionen</a></li>
                <li><a href="<?= htmlspecialchars($hrefs['gallery']) ?>" class="nav-link">Galerie</a></li>
                <li><a href="<?= htmlspecialchars($hrefs['termin']) ?>" class="nav-link">Termin vereinbaren</a></li>
                <li><a href="<?= htmlspecialchars($hrefs['aboutme']) ?>" class="nav-link">Über mich</a></li>
                <li><a href="<?= htmlspecialchars($hrefs['faq']) ?>" class="nav-link">Fragen & Antworten</a></li>
            </ul>

            <ul class="header-social">
                <li>
                    <a class="social-icon" href="https://www.instagram.com/friends.tattoo" target="_blank">
                        <span class="iconify" data-icon="la:instagram"></span>
                    </a>
                </li>
                <li>
                    <a class="social-icon" href="https://www.facebook.com/profile.php?id=100089777867514"
                        target="_blank">
                        <span class="iconify" data-icon="la:facebook-f"></span>
                    </a>
                </li>
                <li>
                    <a class="social-icon" id="whatsapp-link" target="_blank">
                        <span class="iconify" data-icon="la:whatsapp"></span>
                    </a>
                    <script>
                        // Сообщения на разных языках
                        const messages = {
                            de: "Guten Tag, ich habe Ihre Kontaktdaten auf Ihrer Website gefunden und interessiere mich für Tattoos.",
                            ru: "Добрый день, я нашел ваши контакты на вашем интернет-сайте и меня интересуют татуировки.",
                            en: "Hello, I found your contact on your website and I'm interested in tattoos.",
                            uk: "Доброго дня, я знайшов ваші контакти на сайті та цікавлюсь тату.",
                            tr: "Merhaba, web sitenizden iletişim bilgilerinizi buldum ve dövme ile ilgileniyorum."
                        };

                        // Определяем язык браузера
                        const userLang = navigator.language.slice(0, 2);

                        // Подставляем сообщение в зависимости от языка, по умолчанию английский
                        const message = messages[userLang] || messages["en"];

                        // Номер без +
                        const phoneNumber = "4917632565824";

                        // Формируем ссылку на WhatsApp
                        const whatsappLink = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;

                        // Назначаем ссылку элементу
                        document.getElementById("whatsapp-link").href = whatsappLink;
                    </script>
                </li>
            </ul>
        </nav>

        <a class="header-menu-toggle" href="#"><span>Menu</span></a>

    </header>