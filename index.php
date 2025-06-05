<?php
// hospital_app/index.php

session_start(); // Inicia la sesión PHP al principio de todo

// --- Configuración de la Base de Datos (PDO) ---
define('DB_HOST', '127.0.0.1'); // O 'localhost'
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Deja vacío si no tienes contraseña para root en Laragon

$pdo = null; // Inicializa PDO a null
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("<div style='text-align: center; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;'>
            <h1>Error de Conexión a la Base de Datos</h1>
            <p>Por favor, asegúrate de que MySQL está corriendo en Laragon y de que la base de datos 'hospital_db' y las tablas 'citas' y 'users' existen y están configuradas correctamente.</p>
            <p>Detalles del error: " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}

$message = ''; // Variable para mensajes de éxito/error del PHP
$appointments = []; // Para almacenar las citas a mostrar
$currentView = $_GET['view'] ?? 'login'; // Vista por defecto es login

// --- Lógica de Autenticación (Registro y Login) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_action'])) {
    if ($_POST['auth_action'] === 'register') {
        $email = trim($_POST['registerEmail'] ?? '');
        $password = trim($_POST['registerPassword'] ?? '');

        if (empty($email) || empty($password)) {
            $message = "Por favor, complete todos los campos para registrarse.";
        } elseif (strlen($password) < 6) {
            $message = "La contraseña debe tener al menos 6 caracteres.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "El formato del correo electrónico no es válido.";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->execute();
                $message = "Registro exitoso. Ahora puedes iniciar sesión.";
                // Redirigir a la vista de login después del registro exitoso
                $currentView = 'login';
                $_POST = array(); // Limpiar POST para evitar reenvío de formulario
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Código de error para entrada duplicada (UNIQUE constraint)
                    $message = "Error: El correo electrónico ya está registrado.";
                } else {
                    $message = "Error al registrar: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    } elseif ($_POST['auth_action'] === 'login') {
        $email = trim($_POST['loginEmail'] ?? '');
        $password = trim($_POST['loginPassword'] ?? '');

        if (empty($email) || empty($password)) {
            $message = "Por favor, complete todos los campos para iniciar sesión.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $email;
                    $message = "Inicio de sesión exitoso. ¡Bienvenido de nuevo!";
                    $currentView = 'main'; // Redirigir a la página principal
                    $_POST = array(); // Limpiar POST
                } else {
                    $message = "Correo electrónico o contraseña incorrectos.";
                }
            } catch (PDOException $e) {
                $message = "Error al iniciar sesión: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// --- Lógica de Cierre de Sesión ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();   // Elimina todas las variables de sesión
    session_destroy(); // Destruye la sesión
    $message = "Sesión cerrada exitosamente.";
    $currentView = 'login'; // Redirigir a la vista de login
}

// --- Verificar si el usuario está logueado para acceder a las funcionalidades del hospital ---
$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn) {
    // Si está logueado, permitir el acceso a las vistas de citas
    if (isset($_GET['view'])) {
        $currentView = $_GET['view'];
    } else {
        $currentView = 'main'; // Si está logueado y no hay vista especificada, ir a la principal
    }

    // --- Lógica para Registrar Citas (solo si está logueado) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_appointment') {
        $motivo = trim($_POST['motivoVisita'] ?? '');
        $especialidad = trim($_POST['especialidad'] ?? '');
        $nombre = trim($_POST['nombrePaciente'] ?? '');
        $apellido = trim($_POST['apellidoPaciente'] ?? '');
        $dni = trim($_POST['dniPaciente'] ?? '');
        $telefono = trim($_POST['telefonoPaciente'] ?? '');
        $dolencia = trim($_POST['dolencia'] ?? '');
        $fechaCita = trim($_POST['fechaCita'] ?? '');
        $horaCita = trim($_POST['horaCita'] ?? '');

        if (empty($motivo) || empty($especialidad) || empty($nombre) || empty($apellido) || empty($dni) || empty($dolencia) || empty($fechaCita) || empty($horaCita)) {
            $message = "Por favor, complete todos los campos obligatorios para registrar la cita.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO citas (motivo, especialidad, nombre, apellido, dni, telefono, dolencia, fecha_cita, hora_cita) VALUES (:motivo, :especialidad, :nombre, :apellido, :dni, :telefono, :dolencia, :fecha_cita, :hora_cita)");
                $stmt->bindParam(':motivo', $motivo);
                $stmt->bindParam(':especialidad', $especialidad);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':apellido', $apellido);
                $stmt->bindParam(':dni', $dni);
                $stmt->bindParam(':telefono', $telefono);
                $stmt->bindParam(':dolencia', $dolencia);
                $stmt->bindParam(':fecha_cita', $fechaCita);
                $stmt->bindParam(':hora_cita', $horaCita);
                $stmt->execute();
                $message = "Cita registrada exitosamente.";
                $_POST = array(); // Limpiar los campos del formulario
            } catch (PDOException $e) {
                $message = "Error al registrar la cita: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // --- Lógica para Eliminar Citas (solo si está logueado) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_appointment') {
        $idToDelete = $_POST['delete_id'] ?? null;

        if ($idToDelete) {
            try {
                $stmt = $pdo->prepare("DELETE FROM citas WHERE id = :id");
                $stmt->bindParam(':id', $idToDelete, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $message = "Cita eliminada exitosamente.";
                } else {
                    $message = "No se encontró la cita para eliminar.";
                }
            } catch (PDOException $e) {
                $message = "Error al eliminar la cita: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $message = "ID de cita no proporcionado para eliminar.";
        }
    }

    // --- Lógica para Cargar y Filtrar Citas (solo si está logueado) ---
    $searchTerm = trim($_POST['appointmentSearchInput'] ?? $_GET['search'] ?? '');

    try {
        $sql = "SELECT id, motivo, especialidad, nombre, apellido, dni, telefono, dolencia, fecha_cita, hora_cita FROM citas";
        $params = [];

        if (!empty($searchTerm)) {
            $sql .= " WHERE nombre LIKE :searchTermNombre OR apellido LIKE :searchTermApellido OR dni LIKE :searchTermDni";
            $params[':searchTermNombre'] = '%' . $searchTerm . '%';
            $params[':searchTermApellido'] = '%' . $searchTerm . '%';
            $params[':searchTermDni'] = '%' . $searchTerm . '%';
        }
        $sql .= " ORDER BY timestamp_registro DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error al cargar citas: " . htmlspecialchars($e->getMessage());
    }
} else {
    // Si no está logueado, forzar la vista de login/register
    $currentView = 'login';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital TODOS SALUD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to bottom right, #1a202c, #2c5282, #319795);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            color: white;
            overflow-x: hidden;
            padding: 2rem 0;
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(74, 222, 128, 0.7);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 222, 128, 1);
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            max-width: 90%;
            width: 900px;
            text-align: center;
            transition: all 0.3s ease-in-out;
            margin-top: 2rem;
        }
        @media (min-width: 768px) {
            .card.large {
                width: 90%;
                max-width: 1200px;
            }
        }

        .input-field {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: none;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 1rem;
            transition: all 0.2s ease-in-out;
        }

        .input-field::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .input-field:focus {
            outline: none;
            box-shadow: 0 0 0 2px #4ade80;
        }

        .btn {
            background-color: #4ade80;
            color: #1a202c;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            cursor: pointer;
            border: none;
        }

        .btn:hover {
            background-color: #22c55e;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: transparent;
            color: #4ade80;
            border: 2px solid #4ade80;
        }

        .btn-secondary:hover {
            background-color: rgba(74, 222, 128, 0.1);
            color: #22c55e;
        }

        .table-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            margin-top: 2rem;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: white;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        th {
            background-color: rgba(74, 222, 128, 0.2);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: #1a202c;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body class="flex flex-col items-center">
    <header class="w-full py-6 bg-transparent text-center">
        <h1 class="text-5xl md:text-6xl font-extrabold text-teal-400 tracking-tight">HOSPITAL TODOS SALUD</h1>
        <?php if ($isLoggedIn): ?>
            <nav class="mt-4 flex justify-center space-x-4">
                <a href="?view=register" class="btn btn-secondary px-4 py-2">Registrar Nueva Cita</a>
                <a href="?view=list" class="btn btn-secondary px-4 py-2">Ver Citas</a>
                <a href="?action=logout" class="btn btn-secondary px-4 py-2">Cerrar Sesión</a>
            </nav>
        <?php endif; ?>
    </header>

    <?php if (!empty($message)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showModal("<?= htmlspecialchars($message) ?>");
            });
        </script>
    <?php endif; ?>

    <?php if (!$isLoggedIn): // Mostrar formulario de login/registro si no está logueado ?>
        <div id="loginRegisterView" class="card">
            <h2 id="authTitle" class="text-3xl font-bold mb-6 text-white">Regístrate</h2>

            <form id="registerForm" method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="auth_action" value="register">
                <input type="email" id="registerEmail" name="registerEmail" placeholder="Correo Electrónico" class="input-field" value="<?= htmlspecialchars($_POST['registerEmail'] ?? '') ?>" required>
                <input type="password" id="registerPassword" name="registerPassword" placeholder="Contraseña" class="input-field" required>
                <button type="submit" class="btn w-full">Registrar</button>
                <p class="text-white text-sm mt-4">¿Ya tienes cuenta?</p>
                <button type="button" id="showLoginFormBtn" class="btn btn-secondary w-full">Iniciar sesión con correo existente</button>
            </form>

            <form id="loginForm" method="POST" action="index.php" class="space-y-4 hidden">
                <input type="hidden" name="auth_action" value="login">
                <input type="email" id="loginEmail" name="loginEmail" placeholder="Correo Electrónico" class="input-field" value="<?= htmlspecialchars($_POST['loginEmail'] ?? '') ?>" required>
                <input type="password" id="loginPassword" name="loginPassword" placeholder="Contraseña" class="input-field" required>
                <button type="submit" class="btn w-full">Listo</button>
                <p class="text-white text-sm mt-4">¿No tienes cuenta?</p>
                <button type="button" id="showRegisterFormBtn" class="btn btn-secondary w-full">Crear una cuenta</button>
            </form>
        </div>
    <?php elseif ($currentView === 'main'): // Página principal después del login ?>
        <div id="mainPageView" class="card max-w-2xl w-full">
            <h2 class="text-3xl font-bold mb-6 text-white">Bienvenido al Hospital</h2>
            <p class="text-lg mb-4 text-white">¡Tu salud es nuestra prioridad!</p>
            <p class="text-sm mb-6 text-gray-300">Usuario: <span class="font-mono text-teal-300"><?= htmlspecialchars($_SESSION['user_email'] ?? 'N/A') ?></span></p>

            <div class="flex flex-col md:flex-row gap-4 justify-center">
                <a href="?view=register" class="btn flex-1">Registrar Cita</a>
                <a href="?view=list" class="btn btn-secondary flex-1">Ver Citas</a>
            </div>
        </div>
    <?php elseif ($currentView === 'register'): // Formulario de registro de citas ?>
        <div id="appointmentFormView" class="card large">
            <h2 class="text-3xl font-bold mb-6 text-white">Registro de Citas</h2>

            <form method="POST" action="index.php?view=register" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="register_appointment">
                <div>
                    <label for="motivoVisita" class="block text-white text-left text-sm font-bold mb-2">Motivo de la Visita:</label>
                    <select id="motivoVisita" name="motivoVisita" class="input-field" required>
                        <option value="">Seleccione un motivo</option>
                        <option value="Consulta General" <?= (isset($_POST['motivoVisita']) && $_POST['motivoVisita'] == 'Consulta General') ? 'selected' : '' ?>>Consulta General</option>
                        <option value="Revisión Médica" <?= (isset($_POST['motivoVisita']) && $_POST['motivoVisita'] == 'Revisión Médica') ? 'selected' : '' ?>>Revisión Médica</option>
                        <option value="Urgencia" <?= (isset($_POST['motivoVisita']) && $_POST['motivoVisita'] == 'Urgencia') ? 'selected' : '' ?>>Urgencia</option>
                        <option value="Control Post-Operatorio" <?= (isset($_POST['motivoVisita']) && $_POST['motivoVisita'] == 'Control Post-Operatorio') ? 'selected' : '' ?>>Control Post-Operatorio</option>
                        <option value="Exámenes de Laboratorio" <?= (isset($_POST['motivoVisita']) && $_POST['motivoVisita'] == 'Exámenes de Laboratorio') ? 'selected' : '' ?>>Exámenes de Laboratorio</option>
                    </select>
                </div>
                <div>
                    <label for="especialidad" class="block text-white text-left text-sm font-bold mb-2">Especialidad:</label>
                    <select id="especialidad" name="especialidad" class="input-field" required>
                        <option value="">Seleccione una especialidad</option>
                        <option value="Cardiología" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Cardiología') ? 'selected' : '' ?>>Cardiología</option>
                        <option value="Pediatría" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Pediatría') ? 'selected' : '' ?>>Pediatría</option>
                        <option value="Dermatología" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Dermatología') ? 'selected' : '' ?>>Dermatología</option>
                        <option value="Neurología" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Neurología') ? 'selected' : '' ?>>Neurología</option>
                        <option value="Ginecología" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Ginecología') ? 'selected' : '' ?>>Ginecología</option>
                        <option value="Oftalmología" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Oftalmología') ? 'selected' : '' ?>>Oftalmología</option>
                        <option value="Odontología" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Odontología') ? 'selected' : '' ?>>Odontología</option>
                        <option value="Medicina Interna" <?= (isset($_POST['especialidad']) && $_POST['especialidad'] == 'Medicina Interna') ? 'selected' : '' ?>>Medicina Interna</option>
                    </select>
                </div>
                <div>
                    <label for="nombrePaciente" class="block text-white text-left text-sm font-bold mb-2">Nombre:</label>
                    <input type="text" id="nombrePaciente" name="nombrePaciente" placeholder="Nombre del Paciente" class="input-field" value="<?= htmlspecialchars($_POST['nombrePaciente'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="apellidoPaciente" class="block text-white text-left text-sm font-bold mb-2">Apellido:</label>
                    <input type="text" id="apellidoPaciente" name="apellidoPaciente" placeholder="Apellido del Paciente" class="input-field" value="<?= htmlspecialchars($_POST['apellidoPaciente'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="dniPaciente" class="block text-white text-left text-sm font-bold mb-2">DNI/ID (Historia Clínica):</label>
                    <input type="text" id="dniPaciente" name="dniPaciente" placeholder="Número de DNI/ID" class="input-field" value="<?= htmlspecialchars($_POST['dniPaciente'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="telefonoPaciente" class="block text-white text-left text-sm font-bold mb-2">Teléfono:</label>
                    <input type="tel" id="telefonoPaciente" name="telefonoPaciente" placeholder="Número de Teléfono" class="input-field" value="<?= htmlspecialchars($_POST['telefonoPaciente'] ?? '') ?>" >
                </div>
                <div>
                    <label for="fechaCita" class="block text-white text-left text-sm font-bold mb-2">Fecha de Cita:</label>
                    <input type="date" id="fechaCita" name="fechaCita" class="input-field" value="<?= htmlspecialchars($_POST['fechaCita'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="horaCita" class="block text-white text-left text-sm font-bold mb-2">Hora de Cita:</label>
                    <input type="time" id="horaCita" name="horaCita" class="input-field" value="<?= htmlspecialchars($_POST['horaCita'] ?? '') ?>" required>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="dolencia" class="block text-white text-left text-sm font-bold mb-2">Dolencia (8 datos o más):</label>
                    <textarea id="dolencia" name="dolencia" placeholder="Describa su dolencia, síntomas, historial relevante, medicamentos actuales, alergias, cirugías previas, condiciones crónicas, y cualquier otra información importante." rows="4" class="input-field resize-y" required><?= htmlspecialchars($_POST['dolencia'] ?? '') ?></textarea>
                </div>
                <div class="col-span-1 md:col-span-2 flex flex-col md:flex-row gap-4 mt-6">
                    <button type="submit" class="btn flex-1">Registrar Cita</button>
                    <a href="?view=list" class="btn btn-secondary flex-1">Ver Citas</a>
                </div>
            </form>
        </div>
    <?php elseif ($currentView === 'list'): // Vista de listado de citas ?>
        <div id="appointmentListView" class="card large">
            <h2 class="text-3xl font-bold mb-6 text-white">Citas Registradas</h2>

            <form method="POST" action="index.php?view=list" class="mb-4 flex flex-col md:flex-row gap-4 items-center">
                <input type="text" id="appointmentSearchInput" name="appointmentSearchInput" placeholder="Buscar por Nombre, Apellido o DNI/Historia Clínica" class="input-field flex-grow" value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit" class="btn px-6 py-2">Buscar Cita</button>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Motivo</th>
                            <th>Especialidad</th>
                            <th>Paciente</th>
                            <th>DNI/Historia Clínica</th>
                            <th>Teléfono</th>
                            <th>Dolencia</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($appointments) > 0): ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['id']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['motivo']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['especialidad']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['nombre'] . ' ' . $appointment['apellido']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['dni']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['telefono']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars(substr($appointment['dolencia'], 0, 50)) . (strlen($appointment['dolencia']) > 50 ? '...' : '') ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['fecha_cita']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($appointment['hora_cita']) ?></td>
                                    <td class="px-4 py-2">
                                        <button class="btn btn-delete bg-red-600 hover:bg-red-700 text-white px-3 py-1 text-sm rounded-md"
                                                onclick="confirmDeletion(<?= $appointment['id'] ?>, '<?= htmlspecialchars($appointment['nombre'] . ' ' . $appointment['apellido']) ?>')">Eliminar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center py-4">No hay citas registradas que coincidan con la búsqueda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-8">
                <a href="?view=register" class="btn btn-secondary w-full">Registrar Nueva Cita</a>
            </div>
        </div>
    <?php endif; ?>

    <div id="modalOverlay" class="modal-overlay">
        <div class="modal-content">
            <p id="modalMessage" class="text-white text-lg mb-6"></p>
            <div class="flex justify-center gap-4">
                <button id="modalConfirmBtn" class="btn px-6 py-2">Aceptar</button>
                <button id="modalCancelBtn" class="btn btn-secondary px-6 py-2">Cancelar</button>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" action="index.php?view=list" class="hidden">
        <input type="hidden" name="action" value="delete_appointment">
        <input type="hidden" name="delete_id" id="delete_id">
        <input type="hidden" name="appointmentSearchInput" value="<?= htmlspecialchars($searchTerm) ?>">
    </form>

    <script>
        // JavaScript para el modal
        const modalOverlay = document.getElementById('modalOverlay');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let confirmCallback = null;

        function showModal(message, onConfirm = null) {
            modalMessage.textContent = message;
            if (onConfirm) {
                modalConfirmBtn.classList.remove('hidden');
                modalConfirmBtn.textContent = 'Aceptar';
                modalCancelBtn.classList.remove('hidden');
                modalCancelBtn.textContent = 'Cancelar';
            } else {
                modalConfirmBtn.classList.remove('hidden');
                modalConfirmBtn.textContent = 'OK';
                modalCancelBtn.classList.add('hidden');
            }

            confirmCallback = onConfirm;
            modalConfirmBtn.onclick = () => {
                modalOverlay.classList.remove('active');
                if (confirmCallback) confirmCallback(true);
                else hideModal();
            };
            modalCancelBtn.onclick = () => {
                modalOverlay.classList.remove('active');
                if (confirmCallback) confirmCallback(false);
            };
            modalOverlay.classList.add('active');
        }

        function hideModal() {
            modalOverlay.classList.remove('active');
            confirmCallback = null;
        }

        function confirmDeletion(id, name) {
            showModal(`¿Está seguro de que desea eliminar la cita de ${name} (ID: ${id})?`, (confirmed) => {
                if (confirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            }, true);
        }

        // Lógica para alternar entre formularios de login/registro
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const showLoginFormBtn = document.getElementById('showLoginFormBtn');
            const showRegisterFormBtn = document.getElementById('showRegisterFormBtn');
            const authTitle = document.getElementById('authTitle');

            if (showLoginFormBtn) { // Asegurarse de que los elementos existen (solo en la vista de login/register)
                showLoginFormBtn.addEventListener('click', () => {
                    registerForm.classList.add('hidden');
                    loginForm.classList.remove('hidden');
                    authTitle.textContent = 'Inicie Sesión';
                });
            }

            if (showRegisterFormBtn) {
                showRegisterFormBtn.addEventListener('click', () => {
                    loginForm.classList.add('hidden');
                    registerForm.classList.remove('hidden');
                    authTitle.textContent = 'Regístrate';
                });
            }

            // Mantener el formulario de login activo si hubo un error de login
            <?php if (isset($_POST['auth_action']) && $_POST['auth_action'] === 'login' && !empty($message) && strpos($message, 'exitoso') === false): ?>
                if (loginForm) {
                    registerForm.classList.add('hidden');
                    loginForm.classList.remove('hidden');
                    authTitle.textContent = 'Inicie Sesión';
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
