<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QOOQZ Logo - Exact Style</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;900&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8fafc;
        }

        .logo {
            position: relative;
            width: 420px;
            height: 420px;
        }

        /* الدائرة الخارجية السميكة (حرف Q) */
        .q-outer {
            position: absolute;
            width: 340px;
            height: 340px;
            border: 38px solid #1e40af;
            border-radius: 50%;
            top: 40px;
            left: 40px;
            z-index: 1;
        }

        /* الدائرة الداخلية البيضاء */
        .q-inner {
            position: absolute;
            width: 240px;
            height: 240px;
            background: white;
            border-radius: 50%;
            top: 90px;
            left: 90px;
            z-index: 2;
            box-shadow: inset 0 10px 30px rgba(0,0,0,0.1);
        }

        /* العيون البرتقالية (OO) */
        .eye {
            position: absolute;
            top: 135px;
            width: 68px;
            height: 78px;
            background: #ff8a00;
            border-radius: 50%;
            z-index: 3;
            border: 8px solid white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
        }
        .eye.left  { left: 118px; }
        .eye.right { right: 118px; }

        .pupil {
            width: 26px;
            height: 34px;
            background: #1e3a8a;
            border-radius: 50%;
            position: absolute;
            top: 22px;
            left: 21px;
        }

        /* حرف Q الصغير تحت العيون */
        .q-small {
            position: absolute;
            top: 225px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 82px;
            font-weight: 900;
            color: #1e40af;
            z-index: 4;
            line-height: 1;
        }

        /* حرف Z الكبير الملتصق */
        .z-letter {
            position: absolute;
            bottom: 65px;
            right: -35px;
            font-size: 168px;
            font-weight: 900;
            color: #1e40af;
            transform: rotate(-12deg);
            text-shadow: 10px 12px 25px rgba(30, 64, 175, 0.4);
            z-index: 5;
            line-height: 1;
        }
    </style>
</head>
<body>

<div class="logo">
    <!-- الدائرة الخارجية -->
    <div class="q-outer"></div>
    
    <!-- الدائرة الداخلية -->
    <div class="q-inner"></div>
    
    <!-- العيون OO -->
    <div class="eye left"><div class="pupil"></div></div>
    <div class="eye right"><div class="pupil"></div></div>
    
    <!-- حرف Q الصغير -->
    <div class="q-small">Q</div>
    
    <!-- حرف Z -->
    <div class="z-letter">Z</div>
</div>

</body>
</html>