<!DOCTYPE html>
<html lang="pt-br">

<head>
  <link href="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
  <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
  <link rel="stylesheet" href="CSS/EstiloLogin.css">
  <title>Databit</title>
  <link rel="icon" type="image/png" href="./MEDIA/icon.png">
</head>
<!------ Include the above in your HEAD tag ---------->

<body>
  <div id="loadingOverlay" class="loading-overlay" role="status" aria-live="polite" aria-hidden="true">
    <div class="loading-box">
      <div class="spinner-border text-primary mb-3" aria-hidden="true"></div>
      <div><strong>Entrando...</strong></div>
      <div class="small text-muted mt-1">Aguarde um momento</div>
    </div>
  </div>
  <div class="wrapper fadeInDown">
    <div id="formContent">
      <!-- Tabs Titles -->

      <!-- Icon -->
      <div class="fadeIn first">
        <img src="media/logo.png" id="icon" alt="User Icon" />

        <!-- Login Form -->
        <form action="./loginexec.php" method="post" onsubmit="document.getElementById('loadingOverlay').classList.add('is-visible')">
          <input type="text" id="login" class="fadeIn second" name="login" placeholder="Usuario">
          <input type="password" id="password" class="fadeIn third" name="password" placeholder="Senha">
          <input type="submit" class="fadeIn fourth" value="Entrar">
        </form>

        <!-- Remind Passowrd -->
        <div id="formFooter">
          <!-- <a class="underlineHover" href="#">Esqueceu?</a> -->
        </div>

      </div>
    </div>

  <script>
    window.addEventListener('pageshow', function () {
      document.getElementById('loadingOverlay').classList.remove('is-visible');
    });
  </script>

</body>

</html>
