<?php
// This page is just for prototype, no database involved
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Senior Health Risk Prototype</title>
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: #f0f2f5;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.container {
    background: white;
    padding: 30px;
    border-radius: 10px;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

input {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.result {
    padding: 15px;
    border-radius: 5px;
    background: #f8f9fa;
    margin-top: 10px;
}

.risk {
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 10px;
}

.risk.low { color: green; }
.risk.moderate { color: orange; }
.risk.high { color: red; }
.risk.critical { color: darkred; }
</style>
</head>
<body>
<div class="container">
    <h2>Senior Health Risk Prototype</h2>

    <label>Blood Pressure (Systolic mmHg)</label>
    <input type="number" id="bp" placeholder="e.g., 120">

    <label>Blood Sugar (mg/dL)</label>
    <input type="number" id="bs" placeholder="e.g., 100">

    <label>Heart Rate (bpm)</label>
    <input type="number" id="hr" placeholder="e.g., 75">

    <div class="result">
        <div class="risk" id="riskLevel">RISK: -</div>
        <div id="guidance">Guidance: -</div>
    </div>
</div>

<script>
// Get input fields
const bpInput = document.getElementById('bp');
const bsInput = document.getElementById('bs');
const hrInput = document.getElementById('hr');
const riskLevelEl = document.getElementById('riskLevel');
const guidanceEl = document.getElementById('guidance');

function calculateRisk() {
    const bp = parseFloat(bpInput.value);
    const bs = parseFloat(bsInput.value);
    const hr = parseFloat(hrInput.value);

    let risk = 'Low';
    let guidance = 'Routine checkup next month';

    // Blood Pressure
    if(!isNaN(bp)) {
        if(bp >= 180) { risk = 'Critical'; guidance = 'Needs immediate guidance'; }
        else if(bp >= 140) { risk = 'High'; guidance = 'Refer to health center'; }
        else if(bp >= 120) { risk = 'Moderate'; guidance = 'Monitor regularly'; }
    }

    // Blood Sugar
    if(!isNaN(bs)) {
        if(bs >= 126) { risk = risk==='Critical'?'Critical':'High'; guidance = 'Needs immediate guidance / Possible Diabetes'; }
        else if(bs >= 100) { risk = risk==='Low'?'Moderate':risk; guidance = guidance==='Routine checkup next month'?'Monitor diet':guidance; }
    }

    // Heart Rate
    if(!isNaN(hr)) {
        if(hr > 100 || hr < 60) { risk = risk==='Low'?'Moderate':risk; guidance = guidance==='Routine checkup next month'?'Monitor heart rate':guidance; }
    }

    // Update UI
    riskLevelEl.textContent = `RISK: ${risk.toUpperCase()}`;
    riskLevelEl.className = `risk ${risk.toLowerCase()}`;
    guidanceEl.textContent = `Guidance: ${guidance}`;
}

// Listen to inputs
[bpInput, bsInput, hrInput].forEach(input => input.addEventListener('input', calculateRisk));
</script>
</body>
</html>