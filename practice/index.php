<?php
  session_start();
  if(isset($_POST['submit'])){

  } else {

?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
  <head>
    <title>index</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="." />
    <meta name="keywords" content="." />
    <meta name="author" content="Sniper 2025" />
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./dist/assets/fonts/phosphor/duotone/style.css" />
    <link rel="stylesheet" href="./dist/assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="./dist/assets/fonts/feather.css" />
    <link rel="stylesheet" href="./dist/assets/fonts/fontawesome.css" />
    <link rel="stylesheet" href="./dist/assets/fonts/material.css" />
    <link rel="stylesheet" href="./dist/assets/css/style.css" id="main-style-link" />
  </head>
  <body>
    <!-- [ Pre-loader ] start -->
    <div class="fixed bg-transparent">
      <span><img src="./dist/assets/images/bg.jpeg" alt="logo" /></span>
    </div>
    <!-- [ Pre-loader ] End -->
    <div class="auth-main relative">
      <div class="auth-wrapper v1 flex items-center w-full h-full min-h-screen justify-center">
        <form>
          <div class="mt-4 text-center">
            <button type="button" class="btn btn-primary mx-auto shadow-2xl"><a href="./dist/admin/dashboard.php">Dashboard</a></button>
            </div>
        </form>
    </div>
    <!-- [ Main Content ] end -->
    <!-- Required Js -->
    <script src="../dist/assets/js/plugins/simplebar.min.js"></script>
    <script src="../dist/assets/js/plugins/popper.min.js"></script>
    <script src="../dist/assets/js/icon/custom-icon.js"></script>
    <script src="../dist/assets/js/plugins/feather.min.js"></script>
    <script src="../dist/assets/js/component.js"></script>
    <script src="../dist/assets/js/theme.js"></script>
    <script src="../dist/assets/js/script.js"></script>

    <div class="floting-button fixed bottom-[50px] right-[30px] z-[1030]"></div>
    <script>
      layout_change('false');
      layout_theme_sidebar_change('dark');
      change_box_container('false');
      layout_caption_change('true');
      layout_rtl_change('false');
      preset_change('preset-1');
      main_layout_change('vertical');
    </script>
  </body>
</html>
<?php } ?>