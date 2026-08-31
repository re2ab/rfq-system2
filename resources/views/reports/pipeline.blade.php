@extends('layouts.app')
@section('object', 'گزارش')
@section('title', 'گزارش پایپ‌لاین')
@section('actions')
  <a href="{{ route('reports.index') }}" class="btn btn-ghost">بازگشت</a>
@endsection
@section('content')
<div class="card mb-4">
  <div class="card-b">
    <canvas id="pipelineChart" height="120"></canvas>
  </div>
</div>
<div class="card">
  <div class="card-b pad0">
    <table class="tbl">
      <thead><tr><th>وضعیت</th><th>تعداد</th></tr></thead>
      <tbody>
      @foreach($rows as $row)
        <tr><td>{{ $row['label'] }}</td><td class="font-semibold">{{ $row['count'] }}</td></tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = "Vazirmatn, Vazir, Tahoma, sans-serif";
Chart.defaults.font.size = 12;
const labels = @json(collect($rows)->pluck('label'));
const data = @json(collect($rows)->pluck('count'));
new Chart(document.getElementById('pipelineChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'تعداد پرونده',
      data,
      backgroundColor: '#b8703c',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { titleFont: { family: 'Vazirmatn' }, bodyFont: { family: 'Vazirmatn' } }
    },
    scales: {
      x: {
        ticks: { font: { family: 'Vazirmatn, Vazir, Tahoma, sans-serif', size: 11 } }
      },
      y: {
        beginAtZero: true,
        ticks: { font: { family: 'Vazirmatn, Vazir, Tahoma, sans-serif', size: 11 }, precision: 0 }
      }
    }
  }
});
</script>
@endsection
