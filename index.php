<?php include 'head.php'; ?>
<?php include 'headMenu.php'; ?>

<!-- Billboard -->
<section id="home" class="billboard-wrap">

    <div class="billboard-bg-img" id="billboard"
        style="background: url('images/background/banner.jpg'); background-size: cover; background-position: center;">

        <div class="container billboard-content">

            <div class="row">
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    <div class="resized-banner-text">
                        <h1>Wir sind<br>Friends<br>Tattoo.</h1>
                        <p>Ein Tattoo ist mehr als Kunst – es erzählt deine Geschichte. Top Qualität, erfahrene Artists
                            & individuelle Beratung in Langenfeld.</p>
                    </div>

                    <a id="floating-termin-btn" class="btn btn-outline" href="termin.php" role="button">Jetzt Termin
                        sichern<span class="iconify" data-icon="la:arrow-right"></span></a>
                </div>
            </div>

        </div>

    </div>

</section>
<!-- End Billboard -->

<!-- About Us -->
<section id="about" class="gallery-wrap">

    <div class="container">
        <div class="sec-title">
            <h1 class="h1-google">
                <span class="h1-stretch">Das sagen unsere Kunden über uns:</span>
            </h1>
        </div>
    </div>

    <script defer async src='https://cdn.trustindex.io/loader.js?25f321d50cdf9395fa36e6e3cd0'></script>
</section>
<!-- End About Us -->

<!-- Gallery Section -->
<section id="gallery" class="gallery-wrap" style="background: #F9F9F9;">

    <div class="container">
        <div class="sec-title">
            <h1 class="h1-instagram">
                <span class="h1-stretch">Entdecke unsere Galerie auf Instagram:</span>
            </h1>
        </div>
    </div>

    <script defer async src='https://cdn.trustindex.io/loader-feed.js?98b369750ba59392e366f666bbb' onload="
    setTimeout(() => {
        const section = document.getElementById('gallery');
        if (section) {
            section.style.minHeight = section.scrollHeight + 'px';
            /*// Триггерим перерасчёт layout*/
            window.dispatchEvent(new Event('resize'));
            window.scrollBy(0, 1); /*// Микродвижение*/
            window.scrollBy(0, -1);
        }
    }, 2000); /* Ждём немного после загрузки (Trustindex подгружает async) */">
    </script>

</section>
<!-- End Gallery Section -->

<?php include 'footer.php'; ?>

<script src="js/jquery-1.11.0.min.js"></script> 
<script src="https://code.iconify.design/1/1.0.6/iconify.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<!-- <script src="js/plugins.js"></script> -->
<script src="js/script.js"></script>

</body>
</html> 