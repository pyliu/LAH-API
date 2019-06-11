if ($("#table_container")) {
    //$("#balls_canvas").addClass("fade");
    var table = $("#table_container");
    var canvas = document.createElement("canvas");
    table.after(canvas);
    var ctx = canvas.getContext("2d");
    var fps = 100;
    var radius = 8;
    var x = (canvas.width - radius) / Math.floor((Math.random() * 10) + 1);
    var y = (canvas.height - radius) / Math.floor((Math.random() * 10) + 1);
    var dx = 2;
    var dy = -2;
    var x2 = (canvas.width - radius) / Math.floor((Math.random() * 10) + 1);
    var y2 = (canvas.height - radius) / Math.floor((Math.random() * 10) + 1);
    var dx2 = -2;
    var dy2 = 2;
    // JavaScript code goes here
    // resize the canvas to fill browser window dynamically
    window.addEventListener('resize', resizeCanvas, false);

    function resizeCanvas() {
        canvas.width = table.width();
        canvas.height = 35;
        x = (canvas.width - radius) / Math.floor((Math.random() * 10) + 1);
        y = (canvas.height - radius) / Math.floor((Math.random() * 10) + 1);
        x2 = (canvas.width - radius) / Math.floor((Math.random() * 10) + 1);
        y2 = (canvas.height - radius) / Math.floor((Math.random() * 10) + 1);
        /**
         * Your drawings need to be inside this function otherwise they will be reset when 
         * you resize the browser window and the canvas goes will be cleared.
         */
        drawBall();
    }
    resizeCanvas();


    function detectCollision() {
        if (x + dx + radius > canvas.width || x + dx - radius < 0) {
            dx = -dx;
        }
        if (y + dy + radius > canvas.height || y + dy - radius < 0) {
            dy = -dy;
        }
        if (x2 + dx2 + radius > canvas.width || x2 + dx2 - radius < 0) {
            dx2 = -dx2;
        }
        if (y2 + dy2 + radius > canvas.height || y2 + dy2 - radius < 0) {
            dy2 = -dy2;
        }
        // balls collision
        if (
            Math.abs((x + dx) - (x2 + dx2)) <= radius &&
            Math.abs((y + dy) - (y2 + dy2)) <= radius
        ) {
            dx = -dx;
            dy = -dy;
            dx2 = -dx2;
            dy2 = -dy2;
        }
        // fix out of boundary
        if (x < radius) {
            x = (canvas.width - radius) / Math.floor((Math.random() * 10) + 1);
        }
        if (y < radius) {
            y = (canvas.height - radius) / Math.floor((Math.random() * 10) + 1);
        }
        if (x2 < radius) {
            x2 = (canvas.width - radius) / Math.floor((Math.random() * 10) + 1);
        }
        if (y2 < radius) {
            y2 = (canvas.height - radius) / Math.floor((Math.random() * 10) + 1);
        }
    }

    function drawBall() {
        // ball1
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fillStyle = "#0095DD";
        ctx.fill();
        ctx.closePath();
        // ball2
        ctx.beginPath();
        ctx.arc(x2, y2, radius, 0, Math.PI * 2);
        ctx.fillStyle = "#FF9500";
        ctx.fill();
        ctx.closePath();
    }

    function draw() {
        // drawing code
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawBall();
        detectCollision();
        x += dx;
        y += dy;
        x2 += dx2;
        y2 += dy2;
        //console.log("(" + x + "," + y + ")");
    }

    var intval_handle = setInterval(draw, 1000 / fps);
}