<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$message = '';
$message_type = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_expediente = trim($_POST['id_expediente'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $puntuacion = floatval($_POST['puntuacion'] ?? 0);

    // Validaciones
    $errors = [];
    if (empty($id_expediente)) {
        $errors[] = 'El ID de expediente es requerido';
    }
    if (empty($nombre_completo)) {
        $errors[] = 'El nombre completo es requerido';
    }
    if ($puntuacion < 0 || $puntuacion > 100) {
        $errors[] = 'La puntuación debe estar entre 0 y 100';
    }

    if (empty($errors)) {
        try {
            $result = $db->insertExpediente($id_expediente, $nombre_completo, $puntuacion);
            if ($result) {
                $message = 'Expediente guardado correctamente';
                $message_type = 'success';
            } else {
                $message = 'Error al guardar el expediente';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// Obtener expedientes
$expedientes = $db->getAllExpedientes();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Puntuación</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="form-section">
            <h1>📋 Formulario de Puntuación</h1>
            <p class="subtitle">Registro de expedientes y calificaciones</p>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="id_expediente">ID Expediente *</label>
                        <input type="text" id="id_expediente" name="id_expediente" placeholder="Ej: EXP-001" required>
                    </div>

                    <div class="form-group">
                        <label for="nombre_completo">Nombre Completo *</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Ej: Juan Pérez García" required>
                    </div>

                    <div class="form-group">
                        <label for="puntuacion">Puntuación (0-100) *</label>
                        <input type="number" id="puntuacion" name="puntuacion" min="0" max="100" step="0.01" placeholder="Ej: 85.50" required>
                    </div>
                </div>

                <button type="submit">Guardar Expediente</button>
            </form>
        </div>

        <div class="form-section table-section">
            <h2>📊 Expedientes Registrados</h2>

            <?php if (!empty($expedientes)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID Expediente</th>
                            <th>Nombre Completo</th>
                            <th>Puntuación</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expedientes as $index => $exp): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($exp['id_expediente']); ?></td>
                                <td><?php echo htmlspecialchars($exp['nombre_completo']); ?></td>
                                <td class="puntuacion"><?php echo number_format($exp['puntuacion'], 2); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($exp['fecha_creacion'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data">No hay expedientes registrados aún</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
