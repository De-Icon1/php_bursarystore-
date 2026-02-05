<head>
        <meta charset="utf-8" />
        <title>Bursary Store :: appstores</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="A fully featured admin theme which can be used to build HMS, CMS, etc." name="description" />
        <meta content="MartDevelopers" name="author" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="assets/images/OOU.png">

        <!-- Plugins css -->
        <link href="assets/libs/flatpickr/flatpickr.min.css" rel="stylesheet" type="text/css" />

        <!-- App css -->
        <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
         <!-- Loading button css -->
         <link href="assets/libs/ladda/ladda-themeless.min.css" rel="stylesheet" type="text/css" />

        <!-- Footable css -->
        <link href="assets/libs/footable/footable.core.min.css" rel="stylesheet" type="text/css" />

       <!--Load Sweet Alert Javascript-->
       <script src="assets/js/swal.js"></script>
       
        <!-- Fix navbar alignment to top and prevent overlap with forms -->
        <style>
            /* Avoid using flex on body which breaks vertical scrolling in this theme. */
            html, body { margin:0; padding:0; }

            /* Use a fixed navbar so content can scroll normally underneath it. */
            .navbar-custom {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 99999 !important;
                padding: 10px 15px !important;
                background-color: #000 !important;
                border-bottom: 1px solid #333 !important;
                box-shadow: 0 2px 6px rgba(0,0,0,0.08) !important;
            }

            /* Ensure page content is pushed below the fixed navbar */
            body { padding-top: 70px !important; }
            .content, .container, .container-fluid { margin-top: 0 !important; }
            /* Override theme's default hidden overflow so page can scroll normally */
            .content-page { overflow: visible !important; }
            /* Theme sets #wrapper { overflow: hidden } which prevents page scroll — allow scrolling */
            #wrapper { overflow: visible !important; height: auto !important; }
            /* Ensure the left sidebar can scroll independently (slimscroll fallback) */
            .left-side-menu { overflow: auto !important; }
            .left-side-menu .slimscroll-menu {
                max-height: calc(100vh - 100px) !important;
                overflow: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            /* If slimScroll plugin wraps elements, allow wrapper to scroll too */
            .left-side-menu .slimScrollDiv { max-height: calc(100vh - 100px) !important; overflow: auto !important; }
            /* Hide the slimScroll plugin's custom scrollbar (rail + bar) so the native scrollbar is used */
            .slimScrollBar, .slimScrollRail { display: none !important; }
            /* Ensure the plugin wrapper doesn't prevent native scrolling */
            .slimScrollDiv { overflow: visible !important; height: auto !important; }

            /* Keep native scrollbars visible (no overrides). */

            /* Ensure footer is placed after page content and does not overlap when the page scrolls.
               The theme's `.footer` is absolutely positioned; override to flow with document and add
               bottom padding to content area to keep spacing consistent. */
            .footer {
                position: relative !important;
                left: 0 !important;
                right: 0 !important;
                bottom: auto !important;
                width: 100% !important;
                z-index: 1 !important;
                background-color: #eeeff3 !important;
            }

            /* Give content some breathing room so the footer doesn't cover last lines */
            .content-page { padding-bottom: 90px !important; }

            /* Responsive improvements */
            @media (max-width: 768px) {
                .content-page { margin-left: 0 !important; padding: 10px; }
                .card-box { padding: 15px; margin-bottom: 15px; }
                .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
                .form-group { margin-bottom: 15px; }
                .btn { display: block; width: 100%; margin-bottom: 10px; }
                .btn-group .btn { display: inline-block; width: auto; }
                h4, h5 { font-size: 1.2rem; }
                .left-side-menu { margin-left: -240px; transition: margin-left 0.3s; }
                .sidebar-enable .left-side-menu { margin-left: 0; }
            }

            @media (max-width: 576px) {
                .card-box { padding: 10px; }
                .table { font-size: 0.85rem; }
                .btn-sm { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
            }
        </style>
        <style>
            /* Stronger override: use absolute path, high specificity and !important
               to ensure the default theme image is replaced. */
            html body.authentication-bg,
            html body.authentication-bg-pattern,
            body.authentication-bg,
            body.authentication-bg-pattern {
                /* No background image - neutral background */
                background-image: none !important;
                background-repeat: none !important;
                background-position: initial !important;
                background-size: initial !important;
                background-color: #f7f7f7 !important;
                background-attachment: scroll !important;
            }


            /* Keep the authentication card readable and well spaced */
            body.authentication-bg .account-pages,
            body.authentication-bg-pattern .account-pages {
                padding-top: 80px !important;
            }

            /* Ensure card remains readable and not transparent */
            .account-pages .card {
                background-clip: padding-box !important;
            }

            @media (max-width: 768px) {
                body.authentication-bg .account-pages,
                body.authentication-bg-pattern .account-pages {
                    padding-top: 120px !important;
                }
                    html body.authentication-bg,
                    html body.authentication-bg-pattern,
                    body.authentication-bg,
                    body.authentication-bg-pattern {
                        background-size: cover !important;
                        background-position: center center !important;
                    }
            }

            /* Prevent the small card-pattern from showing the old image */
            .bg-pattern,
            .card.bg-pattern {
                background-image: none !important;
                background-color: transparent !important;
            }

            /* Clear any pseudo-element overlays if present */
            html body.authentication-bg-pattern::before,
            html body.authentication-bg::before,
            body.authentication-bg-pattern::before,
            body.authentication-bg::before {
                background: none !important;
                display: none !important;
                content: none !important;
            }
        </style>

        <!-- Background overrides removed - using neutral background -->

        <!--Inject SWAL-->
        <?php if(!empty($success)) {?>
        <!--This code for injecting an alert-->
                <script>
                            setTimeout(function () 
                            { 
                                swal("Success", <?php echo json_encode($success); ?>, "success");
                            },
                                100);
                </script>

        <?php } ?>

        <?php if(!empty($err)) {?>
        <!--This code for injecting an alert-->
                <script>
                            setTimeout(function () 
                            { 
                                swal("Failed", <?php echo json_encode($err); ?>, "error");
                            },
                                100);
                </script>

        <?php } ?>

</head>
<script>
// Sidebar active/focus fixer: runs shortly after page load to ensure
// the correct submenu is expanded based on the current URL or stored link.
document.addEventListener('DOMContentLoaded', function(){
    setTimeout(function(){
        try{
            var current = window.location.href.split(/[?#]/)[0];
            var anchors = document.querySelectorAll('#side-menu a[href]');
            var matched = null;
            anchors.forEach(function(a){
                try{
                    var href = a.href.split(/[?#]/)[0];
                    if(href === current || current.endsWith('/' + a.getAttribute('href'))){
                        matched = a;
                    }
                }catch(e){}
            });

            // fallback to stored href (set on click) if no direct match
            if(!matched && window.localStorage){
                var stored = localStorage.getItem('activeSidebarHref');
                if(stored){
                    anchors.forEach(function(a){ if(a.getAttribute('href') === stored) matched = a; });
                }
            }

            if(matched){
                // clear previous active states
                document.querySelectorAll('#side-menu .active').forEach(function(el){ el.classList.remove('active'); });
                document.querySelectorAll('#side-menu .in').forEach(function(el){ el.classList.remove('in'); });

                // activate the matching anchor and expand its parents using the menu toggler
                // prefer triggering the menu's own toggler so metisMenu handles classes correctly
                matched.classList.add('active');
                var li = matched.parentElement;
                if(li) li.classList.add('active');

                // If inside a nested ul, find the toggler anchor and trigger it
                var parentUl = matched.closest('ul.nav-second-level');
                if(parentUl){
                    var parentLi = parentUl.parentElement; // the li that contains the toggler anchor
                    if(parentLi) parentLi.classList.add('active');
                    var toggler = parentLi ? parentLi.getElementsByTagName('a')[0] : null;
                    if(toggler){
                        // If the submenu is not already visible, trigger the toggler click
                        if(!parentUl.classList.contains('in')){
                            try{ toggler.click(); } catch(e) { /* ignore */ }
                        }
                        // ensure the clicked anchor is highlighted
                        matched.classList.add('active');
                    }
                }
            }

            // save clicks so next page can expand the right menu immediately
            document.querySelectorAll('#side-menu a[href]').forEach(function(a){
                a.addEventListener('click', function(){
                    try{ if(window.localStorage) localStorage.setItem('activeSidebarHref', a.getAttribute('href')); }catch(e){}
                });
            });
        }catch(e){console.error('sidebar-fix error', e);}    
    }, 250);
});
</script>