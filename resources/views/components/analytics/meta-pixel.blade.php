@php
    $settings = app(\App\Domain\Meta\MetaPixelSettingsRepository::class)->get();
@endphp

@if ($settings->canTrack())
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');

        fbq('init', @js($settings->pixelId));
        fbq('track', 'PageView');

        window.chutamaxMetaTrack = function (eventName, parameters) {
            if (typeof fbq !== 'function') {
                return;
            }

            fbq('track', eventName, parameters || {});
        };
    </script>
    <noscript>
        <img
            height="1"
            width="1"
            style="display:none"
            alt=""
            src="https://www.facebook.com/tr?id={{ urlencode($settings->pixelId) }}&ev=PageView&noscript=1"
        >
    </noscript>
@else
    <script>
        window.chutamaxMetaTrack = function () {};
    </script>
@endif
