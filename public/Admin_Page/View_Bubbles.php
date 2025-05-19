<?php
require_once 'config.php';

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$sql = "SELECT id, bubble_name, description, creator_id, created_at FROM bubbles";
$result = $conn->query($sql);

$bubbles = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bubbles[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($bubbles);
$conn->close();
exit;
?>

<script>
function viewBubble(description) {
    document.getElementById('bubbleDescriptionContent').textContent = description;
    var modal = new bootstrap.Modal(document.getElementById('bubbleDescriptionModal'));
    modal.show();
}
</script>

<div class="modal fade" id="bubbleDescriptionModal" tabindex="-1" aria-labelledby="bubbleDescriptionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bubbleDescriptionModalLabel">Bubble Description</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="bubbleDescriptionContent">
        <!-- Description will be injected here -->
      </div>
    </div>
  </div>
</div>
