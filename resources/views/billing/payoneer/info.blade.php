<style>
    .header {
        font-size: 2.5em;
    }
   .success {
       color: #4454df;
   }
   .error {
       color: darkred;
   }
   .text-info {
       color: #555
   }
   .container {
       font-family: sans-serif;
       position: absolute;
       top: 50%;
       left: 50%;
       transform: translate(-50%, -50%);
   }
</style>

<div class="container">
    <h1 class="header {{$success ? 'success' : 'error'}}">{{ $success ? "Payoneer Service Authenticated Successfully" : "An error has occurred" }}</h1>

    @if(isset($message))
        <p style="color: red;">{{ $message }}</p>
    @else
        <p class="text-info">You will be automatically redirected in <span id="time">3</span> seconds</p>
        <p>Click <a id="redirect-link" href="{{ env('APP_URL') . '/billing' }}">here</a> if you were not redirected</p>
    @endif
</div>


<script>
    const redirectLink = document.getElementById('redirect-link').getAttribute('href');
    const timeValue = document.getElementById('time');
    let time = Number(document.getElementById('time').textContent);

    setInterval(() => {
        time--;
        timeValue.textContent = String(time);
    }, 1000)

    setTimeout(() => {
        window.location.href = redirectLink;
    }, 3000);
</script>
