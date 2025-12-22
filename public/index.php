<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$response_message = '';
$checklist_items = [];
$nombres_unicos = [];
$expedientes_filtrados = [];
$mostrar_filtrados = false;
$puntuacion_total = 0;
$nombre_filtro = '';

// Obtener items del checklist
try {
    $checklist_items = $db->getChecklistItems();
} catch (Exception $e) {
    $response_message = '<div style="color: orange; padding: 10px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">⚠ Aviso: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Obtener nombres únicos
try {
    $nombres_unicos = $db->getNombresUnicos();
} catch (Exception $e) {
    // Silenciar errores al obtener nombres
}

// Mostrar mensaje de éxito si viene del redirect
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $response_message = '<div style="color: green; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">✓ Expediente guardado correctamente</div>';
}


// Procesar búsqueda filtrada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buscar') {
    $nombre_filtro = trim($_POST['nombre_filtro'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');

    if (!empty($nombre_filtro) && !empty($fecha_inicio) && !empty($fecha_fin)) {
        try {
            $expedientes_filtrados = $db->getExpedientesFiltrados($nombre_filtro, $fecha_inicio, $fecha_fin);
            $mostrar_filtrados = true;
            
            // Calcular puntuación total
            $puntuacion_total = 0;
            foreach ($expedientes_filtrados as $exp) {
                $puntuacion_total += $exp['puntuacion'];
            }
        } catch (Exception $e) {
            $response_message = '<div style="color: red; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $response_message = '<div style="color: orange; padding: 10px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">⚠ Por favor complete todos los campos de búsqueda</div>';
    }
}

// Procesar formulario de crear expediente (solo si no es búsqueda)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'buscar')) {
    $id_expediente = trim($_POST['id_expediente'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $fecha_expediente = trim($_POST['fecha_expediente'] ?? '');
    
    // Validaciones
    $errors = [];
    if (empty($id_expediente)) {
        $errors[] = 'El ID de expediente es requerido';
    }
    if (empty($nombre_completo)) {
        $errors[] = 'El nombre completo es requerido';
    }

    if (empty($errors)) {
        try {
            // Mapeo de nombres de campos a IDs de items del checklist
            $field_to_item = [
                'llego_a_tiempo' => 1,
                'informo_aseg_tram' => 2,
                'fotos_antes' => 3,
                'localizo_averia' => 4,
                'foto_durante' => 5,
                'reparo_primera' => 6,
                'llamo_encargado_1' => 7,
                'justificado' => 8,
                'foto_despues' => 9,
                'segundo_gremio' => 10,
                'tomo_datos' => 11,
                'tomo_medidas' => 12,
                'firma_asegurado' => 13,
                'expediente_cerrado' => 14,
                'llamo_encargado_2' => 7  // Mismo item que llamo_encargado_1
            ];

            // Preparar respuestas del checklist
            $respuestas = [];
            foreach ($field_to_item as $field_name => $item_id) {
                $value = $_POST[$field_name] ?? null;
                if ($value !== null) {
                    $respuestas[$item_id] = $value === '1';
                }
            }

            // Calcular puntuación basada en respuestas
            $puntuacion = 0;
            $llamo_encargado_counted = false;

            foreach ($checklist_items as $item) {
                // Saltar el item 7 (Llamó encargado) de la iteración normal
                // lo manejaremos especialmente
                if ($item['id'] === 7) {
                    continue;
                }

                if (isset($respuestas[$item['id']]) && $respuestas[$item['id']]) {
                    $puntuacion += $item['puntos_si'];
                }
            }

            // Manejar "Llamó a encargado" especialmente
            // Sumar 1.00 si CUALQUIERA de los dos está marcado
            $llamo_enc_1_marked = isset($respuestas[7]) && $respuestas[7];
            $llamo_enc_2_marked = isset($_POST['llamo_encargado_2']) && $_POST['llamo_encargado_2'] === '1';

            if ($llamo_enc_1_marked || $llamo_enc_2_marked) {
                $puntuacion += 1.00;
            }

            // Preparar datos para insertar
            $params = [
                'id_expediente' => $id_expediente,
                'nombre_completo' => $nombre_completo,
                'puntuacion' => $puntuacion,
                'fecha_expediente' => $fecha_expediente,
                'respuestas' => $respuestas
            ];
            
            // Insertar expediente
            $result = $db->insertExpediente($params);
            
            if ($result) {
                // Limpiar el POST y redirigir para evitar duplicados
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                exit();
            }
        } catch (Exception $e) {
            $response_message = '<div style="color: red; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $response_message = '<div style="color: red; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">✗ Errores: ' . htmlspecialchars(implode(", ", $errors)) . '</div>';
    }
}
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
        <div class="logo-container">
            <img src="/img/logo.png" alt="Logo" class="logo">
        </div>
        <h1>Formulario de Evaluación</h1>
        <?php echo $response_message; ?>
        <form id="evaluationForm" method="POST">
            
            <!-- Datos básicos -->
            <div class="form-section">
                <h3>Datos básicos</h3>
                <div class="form-group">
                    <label for="id_expediente">ID Expediente:</label>
                    <input type="text" id="id_expediente" name="id_expediente" required>
                </div>
                <div class="form-group">
                    <label for="fecha_expediente">Fecha Expediente:</label>
                    <input type="date" id="fecha_expediente" name="fecha_expediente" required>
                </div>
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo:</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required>
                </div>
                
            </div>

            <!-- Checklist Items - Estructura Condicional -->
            <div class="form-section">
                <h3>Checklist de Evaluación</h3>
                
                <!-- LLEGO_A_TIEMPO -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Llegó a tiempo?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="llego_a_tiempo" value="1" class="conditional-trigger"> Sí</label>
                        <label><input type="radio" name="llego_a_tiempo" value="0" class="conditional-trigger"> No</label>
                    </div>
                </div>

                <!-- INFORMO_ASEG_TRAM (mostrar si llego_a_tiempo = 0) -->
                <div id="group_informo_aseg" class="checklist-item conditional-item hidden" style="margin-left: 30px;">
                    <label class="checklist-label">└─ ¿Informó a aseguradora y tramitadora?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="informo_aseg_tram" value="1"> Sí</label>
                        <label><input type="radio" name="informo_aseg_tram" value="0"> No</label>
                    </div>
                </div>

                <!-- FOTOS_ANTES -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Fotos antes?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="fotos_antes" value="1"> Sí</label>
                        <label><input type="radio" name="fotos_antes" value="0"> No</label>
                    </div>
                </div>

                <!-- LOCALIZO_AVERIA -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Localizó avería?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="localizo_averia" value="1" class="conditional-trigger"> Sí</label>
                        <label><input type="radio" name="localizo_averia" value="0" class="conditional-trigger"> No</label>
                    </div>
                </div>

                <!-- LLAMO_ENCARGADO - Para localizo_averia (mostrar si localizo_averia = 0) -->
                <div id="group_llamo_encargado_1" class="checklist-item conditional-item hidden" style="margin-left: 30px;">
                    <label class="checklist-label">└─ ¿Llamó a encargado?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="llamo_encargado_1" value="1"> Sí</label>
                        <label><input type="radio" name="llamo_encargado_1" value="0"> No</label>
                    </div>
                </div>

                <!-- FOTO_DURANTE -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Foto durante?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="foto_durante" value="1"> Sí</label>
                        <label><input type="radio" name="foto_durante" value="0"> No</label>
                    </div>
                </div>

                <!-- REPARO_PRIMERA -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Reparó en 1ª visita?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="reparo_primera" value="1" class="conditional-trigger"> Sí</label>
                        <label><input type="radio" name="reparo_primera" value="0" class="conditional-trigger"> No</label>
                    </div>
                </div>

                <!-- Grupo SI - Reparó en 1ª visita (mostrar si reparo_primera = 1) -->
                <div id="group_reparo_si" class="conditional-group hidden">
                    <div class="checklist-item" style="margin-left: 30px;">
                        <label class="checklist-label">└─ ¿Foto después?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="foto_despues" value="1"> Sí</label>
                            <label><input type="radio" name="foto_despues" value="0"> No</label>
                        </div>
                    </div>

                    <div class="checklist-item" style="margin-left: 30px;">
                        <label class="checklist-label">└─ ¿Segundo gremio?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="segundo_gremio" value="1"> Sí</label>
                            <label><input type="radio" name="segundo_gremio" value="0"> No</label>
                        </div>
                    </div>

                    <div class="checklist-item" style="margin-left: 30px;">
                        <label class="checklist-label">└─ ¿Tomó datos del perjudicado?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="tomo_datos" value="1"> Sí</label>
                            <label><input type="radio" name="tomo_datos" value="0"> No</label>
                        </div>
                    </div>

                    <div class="checklist-item" style="margin-left: 30px;">
                        <label class="checklist-label">└─ ¿Tomó medidas, estancias y pavimento?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="tomo_medidas" value="1"> Sí</label>
                            <label><input type="radio" name="tomo_medidas" value="0"> No</label>
                        </div>
                    </div>
                </div>

                <!-- Grupo NO - No reparó en 1ª visita (mostrar si reparo_primera = 0) -->
                <div id="group_reparo_no" class="conditional-group hidden">
                    <div class="checklist-item" style="margin-left: 30px;">
                        <label class="checklist-label">└─ ¿Llamó a encargado?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="llamo_encargado_2" value="1"> Sí</label>
                            <label><input type="radio" name="llamo_encargado_2" value="0"> No</label>
                        </div>
                    </div>

                    <div class="checklist-item" style="margin-left: 30px;">
                        <label class="checklist-label">└─ ¿Reparación justificada?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="justificado" value="1"> Sí</label>
                            <label><input type="radio" name="justificado" value="0"> No</label>
                        </div>
                    </div>
                </div>

                <!-- FIRMA_ASEGURADO -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Firma del asegurado?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="firma_asegurado" value="1"> Sí</label>
                        <label><input type="radio" name="firma_asegurado" value="0"> No</label>
                    </div>
                </div>

                <!-- EXPEDIENTE_CERRADO -->
                <div class="checklist-item">
                    <label class="checklist-label">¿Se ha cerrado expediente?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="expediente_cerrado" value="1"> Sí</label>
                        <label><input type="radio" name="expediente_cerrado" value="0"> No</label>
                    </div>
                </div>

            </div>

            <!-- Puntuación calculada -->
            <div class="form-section">
                <div class="form-group">
                    <label for="puntuacion">Puntuación Total:</label>
                    <input type="number" id="puntuacion" name="puntuacion" step="0.01" readonly>
                </div>
            </div>
            
            <button type="submit">Guardar Expediente</button>
        </form>
    </div>
    
    <!-- <div class="container">
        <h2>Expedientes guardados</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ID Expediente</th>
                    <th>Nombre Completo</th>
                    <th>Puntuación</th>
                    <th>Fecha Expediente</th>
                    <th>Fecha Creación</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // try {
                //     $expedientes = $db->getAllExpedientes();
                    
                //     if (empty($expedientes)) {
                //         echo "<tr><td colspan='6'>No hay expedientes guardados</td></tr>";
                //     } else {
                //         foreach ($expedientes as $exp) {
                //             echo "<tr>";
                //             echo "<td>" . htmlspecialchars($exp['id']) . "</td>";
                //             echo "<td>" . htmlspecialchars($exp['id_expediente']) . "</td>";
                //             echo "<td>" . htmlspecialchars($exp['nombre_completo']) . "</td>";
                //             echo "<td>" . number_format($exp['puntuacion'], 2) . "</td>";
                //             echo "<td>" . htmlspecialchars($exp['fecha_expediente'] ?? '-') . "</td>";
                //             echo "<td>" . htmlspecialchars($exp['fecha_creacion']) . "</td>";
                //             echo "</tr>";
                //         }
                //     }
                // } catch (Exception $e) {
                //     echo "<tr><td colspan='6'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                // }
                ?>
            </tbody>
        </table>
    </div> -->

    <!-- Formulario de Búsqueda Filtrada -->
    <div class="container">
        <h2>Buscar Expedientes</h2>
        <form method="POST" id="searchForm">
            <input type="hidden" name="action" value="buscar">
            <div class="form-section">
                <div class="form-group">
                    <label for="nombre_filtro">Nombre Completo:</label>
                    <select id="nombre_filtro" name="nombre_filtro" required>
                        <option value="">-- Selecciona un nombre --</option>
                        <?php foreach ($nombres_unicos as $nombre): ?>
                            <option value="<?php echo htmlspecialchars($nombre['nombre_completo']); ?>">
                                <?php echo htmlspecialchars($nombre['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                </div>

                <div class="form-group">
                    <label for="fecha_fin">Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" required>
                </div>

                <button type="submit" class="search-button">Buscar</button>
            </div>
        </form>
    </div>

    <!-- Resultados Filtrados -->
    <?php if ($mostrar_filtrados): ?>
    <div class="container">
        <h2>Resultados de Búsqueda - <?php echo htmlspecialchars($nombre_filtro); ?> (Puntuación Total: <?php echo number_format($puntuacion_total, 2); ?>)</h2>
        <?php if (empty($expedientes_filtrados)): ?>
            <p style="padding: 20px; background-color: #f5f5f5; border-radius: 4px; text-align: center;">No se encontraron expedientes con los criterios especificados.</p>
        <?php else: ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ID Expediente</th>
                        <th>Nombre Completo</th>
                        <th>Puntuación</th>
                        <th>Fecha Expediente</th>
                        <th>Fecha Creación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expedientes_filtrados as $exp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exp['id']); ?></td>
                            <td><?php echo htmlspecialchars($exp['id_expediente']); ?></td>
                            <td><?php echo htmlspecialchars($exp['nombre_completo']); ?></td>
                            <td><?php echo number_format($exp['puntuacion'], 2); ?></td>
                            <td><?php echo htmlspecialchars($exp['fecha_expediente'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($exp['fecha_creacion']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
        // Función para actualizar visibilidad de elementos condicionales
        function updateConditionalFields() {
            // LLEGO_A_TIEMPO = 0 (No) → mostrar INFORMO_ASEG_TRAM
            const llegoAtiempo = document.querySelector('input[name="llego_a_tiempo"]:checked');
            const groupInformo = document.getElementById('group_informo_aseg');
            if (llegoAtiempo) {
                groupInformo.classList.toggle('hidden', llegoAtiempo.value !== '0');
            }

            // LOCALIZO_AVERIA = 0 (No) → mostrar LLAMO_ENCARGADO_1
            const localizoAveria = document.querySelector('input[name="localizo_averia"]:checked');
            const groupLlamoEnc1 = document.getElementById('group_llamo_encargado_1');
            if (localizoAveria) {
                groupLlamoEnc1.classList.toggle('hidden', localizoAveria.value !== '0');
            }

            // REPARO_PRIMERA = 1 (Sí) → mostrar grupo SI
            // REPARO_PRIMERA = 0 (No) → mostrar grupo NO
            const reparoPrimera = document.querySelector('input[name="reparo_primera"]:checked');
            const groupReparoSi = document.getElementById('group_reparo_si');
            const groupReparoNo = document.getElementById('group_reparo_no');
            
            if (reparoPrimera) {
                if (reparoPrimera.value === '1') {
                    groupReparoSi.classList.remove('hidden');
                    groupReparoNo.classList.add('hidden');
                } else {
                    groupReparoSi.classList.add('hidden');
                    groupReparoNo.classList.remove('hidden');
                }
            }
        }

        // Función para verificar si un elemento es visible
        function isVisible(element) {
            // Recorrer el árbol DOM hacia arriba para verificar si algún elemento tiene la clase 'hidden'
            let current = element;
            while (current) {
                if (current.classList && current.classList.contains('hidden')) {
                    return false;
                }
                current = current.parentElement;
            }
            return true;
        }

        // Puntuación con mapeo de items
        function calculateScore() {
            let total = 0;

            // Items individuales con sus puntos
            const itemPoints = {
                'llego_a_tiempo': 1.00,
                'informo_aseg_tram': 0.50,
                'fotos_antes': 0.50,
                'localizo_averia': 1.00,
                'foto_durante': 0.50,
                'reparo_primera': 1.00,
                'justificado': 0.50,
                'foto_despues': 0.50,
                'segundo_gremio': 0.33,
                'tomo_datos': 0.33,
                'tomo_medidas': 0.33,
                'firma_asegurado': 0.25,
                'expediente_cerrado': 0.25
            };

            // Sumar puntos de items individuales marcados como "Sí" (value="1") y que sean visibles
            for (const [fieldName, points] of Object.entries(itemPoints)) {
                const radio = document.querySelector(`input[name="${fieldName}"]:checked`);
                if (radio && radio.value === '1' && isVisible(radio)) {
                    total += points;
                }
            }

            // Sumar "Llamó a encargado" - contar si CUALQUIERA de los dos está marcado como Sí (solo si es visible)
            const llamoEnc1 = document.querySelector('input[name="llamo_encargado_1"]:checked');
            const llamoEnc2 = document.querySelector('input[name="llamo_encargado_2"]:checked');
            
            let llamoEncVisible = false;
            if (llamoEnc1 && llamoEnc1.value === '1' && isVisible(llamoEnc1)) {
                llamoEncVisible = true;
            }
            if (llamoEnc2 && llamoEnc2.value === '1' && isVisible(llamoEnc2)) {
                llamoEncVisible = true;
            }
            
            if (llamoEncVisible) {
                total += 1.00;  // Suma 1.00 total, no 0.50 + 0.50
            }

            document.getElementById('puntuacion').value = total.toFixed(2);
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add change listeners to all radio buttons
            document.querySelectorAll('.conditional-trigger').forEach(radio => {
                radio.addEventListener('change', updateConditionalFields);
            });

            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', calculateScore);
            });
            
            // Calcular puntuación inicial
            calculateScore();
        });
    </script>
</body>
</html>

