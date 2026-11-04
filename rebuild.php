<?php
$chart = file_get_contents("user_panel_chart.php");
// Find the cut point - end of the table div closing
$cutPoint = strpos($chart, "  </div>\n\n  <?php if (!\$isPublic): ?>\n  <nav");
if ($cutPoint === false) {
    // try with \r\n
    $cutPoint = strpos($chart, "  </div>\r\n\r\n  <?php if (!\$isPublic): ?>\r\n  <nav");
}
if ($cutPoint === false) {
    // fallback: find last </div> before nav
    $navPos = strpos($chart, "<nav class=\"bottom-menu\">");
    $cutPoint = strrpos(substr($chart, 0, $navPos), "</div>") + $navPos - strlen(substr($chart, 0, $navPos)) + strlen("</div>\n");
}
echo "Cut point: " . $cutPoint . "\n";
echo "Content around cut:\n";
echo substr($chart, $cutPoint-20, 100);

