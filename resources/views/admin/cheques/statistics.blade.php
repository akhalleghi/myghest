<section class="cq-statistics" style="border:1px solid #0d9488;border-radius:12px;padding:1rem;margin:1rem 0;background:color-mix(in srgb,#0d9488 6%,var(--bg-card))">
    <h3>آمار مستقل فروش چکی</h3><p style="font-size:.8rem;color:var(--muted)">مبالغ این بخش با آمار وام و اقساط جمع نمی‌شوند. مبنای دوره‌ها، سررسید چک و تقویم شمسی است.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
        @foreach($chequeStatistics['rows'] as $row)<article style="padding:.7rem;border-radius:8px;background:var(--bg-card)"><small style="display:block">{{ $row['label'] }}</small><strong>{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers(number_format($row['amount'])) }} تومان</strong><small style="display:block">{{ \Hekmatinasser\Jalali\Jalali::enToFaNumbers((string)$row['count']) }} فقره</small></article>@endforeach
    </div>
    @if(!empty($showChequeTables))
    @foreach($chequeStatistics['charts'] as $chart)<details style="margin-top:1rem"><summary>{{ $chart['title'] }}</summary><div class="cq-mini-chart" role="img" aria-label="{{ $chart['title'] }}">@php($max=max(1, max(array_column($chart['series'],'value')))) @foreach($chart['series'] as $point)<div><span>{{ $point['period'] }}</span><span style="background:#0d9488;height:10px;width:{{ max(1,100*$point['value']/$max) }}%;display:block;border-radius:5px"></span><strong>{{ $point['valueLabel'] }}</strong></div>@endforeach</div></details>@endforeach
    @endif
</section>
