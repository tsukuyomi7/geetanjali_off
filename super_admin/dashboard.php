    <?php
    // geetanjali_website/super_admin/dashboard.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../includes/db_connection.php'; 
    require_once '../includes/functions.php';     

    enforceRoleAccess(['super_admin']);

    $pageTitle = "Super Admin Dashboard";
    // $additional_css[] = BASE_URL . 'css/admin_terminal.css'; // If you create a separate CSS file

    // Fetch some stats (examples)
    $total_users_query = "SELECT COUNT(*) as total FROM users";
    $total_users_result = mysqli_query($conn, $total_users_query);
    $total_users = ($total_users_result) ? mysqli_fetch_assoc($total_users_result)['total'] : 0;

    $total_events_query = "SELECT COUNT(*) as total FROM events";
    $total_events_result = mysqli_query($conn, $total_events_query);
    $total_events = ($total_events_result) ? mysqli_fetch_assoc($total_events_result)['total'] : 0;

    $total_blog_posts_query = "SELECT COUNT(*) as total FROM blog_posts WHERE is_published = TRUE";
    $total_blog_posts_result = mysqli_query($conn, $total_blog_posts_query);
    $total_blog_posts = ($total_blog_posts_result) ? mysqli_fetch_assoc($total_blog_posts_result)['total'] : 0;

    include '../includes/header.php'; 
    ?>

    <main id="page-content" class="admin-area">
        <div class="container">
            <section class="page-title-section">
                <h1><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="subtitle">Oversee and manage all aspects of the Geetanjali website.</p>
            </section>

            <section class="admin-stats-cards content-section">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Users</h3>
                    <p><?php echo $total_users; ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Total Events</h3>
                    <p><?php echo $total_events; ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-newspaper"></i>
                    <h3>Published Blog Posts</h3>
                    <p><?php echo $total_blog_posts; ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-cogs"></i>
                    <h3>System Health</h3>
                    <p class="text-success">Nominal</p> 
                </div>
            </section>

            <section class="admin-quick-links content-section">
                <h2>Quick Management Links</h2>
                <div class="quick-links-grid">
                    <a href="<?php echo BASE_URL; ?>super_admin/manage_users.php" class="quick-link-item">
                        <i class="fas fa-users-cog"></i> Manage Users & Roles
                    </a>
                    <a href="<?php echo BASE_URL; ?>super_admin/verify_users.php" class="quick-link-item">
                        <i class="fas fa-user-check"></i> Verify Student Accounts
                    </a>
                     <a href="<?php echo BASE_URL; ?>admin/events.php" class="quick-link-item">
                        <i class="fas fa-calendar-plus"></i> Manage Events (Admin)
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/blogs.php" class="quick-link-item">
                        <i class="fas fa-edit"></i> Manage Blog Posts (Admin)
                    </a>
                    <a href="<?php echo BASE_URL; ?>super_admin/settings.php" class="quick-link-item">
                        <i class="fas fa-sliders-h"></i> Global Website Settings
                    </a>
                    <a href="<?php echo BASE_URL; ?>super_admin/audit_log.php" class="quick-link-item">
                        <i class="fas fa-history"></i> View Audit Log
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/gallery.php" class="quick-link-item">
                        <i class="fas fa-images"></i> Manage Gallery (Admin)
                    </a>
                     <a href="<?php echo BASE_URL; ?>admin/certificates.php" class="quick-link-item">
                        <i class="fas fa-certificate"></i> Manage Certificates (Admin)
                    </a>
                </div>
            </section>
            
            <section class="admin-terminal-widget content-section card-style-admin" data-aos="fade-up">
                <h2 class="section-title-minor"><i class="fas fa-terminal"></i> Admin Terminal (Client-Side Simulation)</h2>
                <p class="text-muted small-text">This is a client-side simulated terminal. For actual server commands, backend integration is required.</p>
                
                <div id="adminTerminal" onclick="adminTerminalFocusInput()">
                  <div id="adminTerminalToolbar">
                    <span id="adminTerminalName">Geetanjali Admin Shell</span>
                    <div>
                      <div class="admin-window-button" onclick="adminTerminalMinimize()">-</div>
                      <div class="admin-window-button" onclick="adminTerminalMaximize()">+</div>
                      <div class="admin-window-button" onclick="adminTerminalClose()">x</div>
                    </div>
                  </div>
                  <div id="adminTerminalOutput"></div>
                  <div id="adminTerminalInputContainer">
                    <span id="adminTerminalPrompt" class="prompt-user">admin@geetanjali:~$</span>
                    <input
                      type="text"
                      id="adminTerminalInput"
                      autocomplete="off"
                      onkeydown="adminTerminalProcessKey(event)"
                      oninput="adminTerminalAutocompleteInput()"
                    />
                  </div>
                  <div id="adminTerminalAutocomplete"></div>
                  <div id="adminTerminalResizeHandle"></div>
                </div>
            </section>
            <section class="recent-activity content-section alt-background">
                <h2>Recent Activity (Placeholder)</h2>
                <p>This section could display recent audit log entries or important notifications.</p>
                <ul>
                    <li>User 'admin_test' updated event 'Annual Fest'.</li>
                    <li>New user 'student_new' registered.</li>
                </ul>
            </section>

        </div>
    </main>

    <script>
    // This script should ideally be in a separate JS file, loaded only on this page,
    // or within the main script.js wrapped in a check for the existence of #adminTerminal.
    document.addEventListener('DOMContentLoaded', function() {
        const adminTerminalElement = document.getElementById("adminTerminal");

        if (adminTerminalElement) {
            const adminTerminalOutput = document.getElementById("adminTerminalOutput");
            const adminTerminalInput = document.getElementById("adminTerminalInput");
            const adminTerminalAutocomplete = document.getElementById("adminTerminalAutocomplete");
            const adminTerminalPrompt = document.getElementById("adminTerminalPrompt");
            const adminTerminalToolbar = document.getElementById("adminTerminalToolbar");
            const adminTerminalResizeHandle = document.getElementById("adminTerminalResizeHandle");


            let currentPath = "/home/admin"; // Simulated current path
            const simulatedFileSystem = {
                "/": { type: "dir", content: ["home", "var", "etc", "README.txt"] },
                "/home": { type: "dir", content: ["admin", "guest"] },
                "/home/admin": { type: "dir", content: ["documents", "scripts", "config.txt"] },
                "/home/admin/documents": { type: "dir", content: ["report.docx", "notes.txt"] },
                "/home/admin/scripts": { type: "dir", content: ["backup.sh"] },
                "/var": { type: "dir", content: ["log"] },
                "/var/log": { type: "dir", content: ["system.log", "app.log"] },
                "/etc": { type: "dir", content: ["hosts", "php.ini"] },
                "/README.txt": { type: "file", content: "Welcome to Geetanjali Admin Terminal!\nThis is a simulated environment.\nAvailable commands: ls, cd, clear, cat, echo, pwd, help, theme" },
                "/home/admin/config.txt": { type: "file", content: "SITE_NAME=Geetanjali\nADMIN_EMAIL=admin@example.com" },
                "/var/log/system.log": { type: "file", content: "[INFO] System boot\n[WARN] Low disk space" },
            };

            // Helper to resolve path
            function resolvePath(path, base = currentPath) {
                if (path.startsWith('/')) return path; // Absolute path
                const parts = base.split('/').filter(p => p);
                const pathParts = path.split('/').filter(p => p);
                for (const part of pathParts) {
                    if (part === '.') continue;
                    if (part === '..') {
                        if (parts.length > 0) parts.pop();
                    } else {
                        parts.push(part);
                    }
                }
                return '/' + parts.join('/') || '/';
            }

            // Helper to get content of a path
            function getPathContent(path) {
                const resolved = resolvePath(path);
                return simulatedFileSystem[resolved];
            }


            const adminCommands = {
                help: () => {
                    let helpText = "Available commands:<br>";
                    Object.keys(adminCommands).sort().forEach(cmd => {
                        helpText += `- ${cmd}<br>`;
                    });
                    return helpText;
                },
                ls: (args) => {
                    const pathToList = args[1] ? resolvePath(args[1]) : currentPath;
                    const node = simulatedFileSystem[pathToList];
                    if (node && node.type === "dir") {
                        return node.content.map(item => {
                            const itemNode = simulatedFileSystem[resolvePath(item, pathToList)];
                            return itemNode && itemNode.type === "dir" ? `<span style="color: #87cefa;">${item}/</span>` : item;
                        }).join("&nbsp;&nbsp;&nbsp;");
                    }
                    return args[1] ? `ls: cannot access '${args[1]}': No such file or directory` : "Error listing directory contents.";
                },
                cd: (args) => {
                    if (args[1]) {
                        const newPathAttempt = resolvePath(args[1]);
                        const node = simulatedFileSystem[newPathAttempt];
                        if (node && node.type === "dir") {
                            currentPath = newPathAttempt;
                            adminTerminalPrompt.textContent = `admin@geetanjali:${currentPath}$`;
                            return ""; // No output on successful cd
                        } else {
                            return `cd: ${args[1]}: No such file or directory`;
                        }
                    } else { // cd to home
                        currentPath = "/home/admin";
                        adminTerminalPrompt.textContent = `admin@geetanjali:${currentPath}$`;
                        return "";
                    }
                },
                clear: () => { adminTerminalOutput.innerHTML = ""; return ""; },
                cat: (args) => {
                    if (args[1]) {
                        const pathToFile = resolvePath(args[1]);
                        const node = simulatedFileSystem[pathToFile];
                        if (node && node.type === "file") {
                            return node.content.replace(/\n/g, "<br>");
                        }
                        return `cat: ${args[1]}: No such file or directory`;
                    }
                    return "Usage: cat [file]";
                },
                echo: (args) => args.slice(1).join(" "),
                pwd: () => currentPath,
                whoami: () => "super_admin", // Or fetch from PHP session
                date: () => new Date().toString(),
                theme: (args) => {
                    if (args[1] === 'dark' || args[1] === 'light' || args[1] === 'matrix') {
                        adminTerminalElement.className = `theme-${args[1]}`; // Assumes CSS classes theme-dark, theme-light
                        return `Theme set to ${args[1]}`;
                    } else if (args[1] === 'reset') {
                        adminTerminalElement.className = ''; // Reset to default
                        return `Theme reset to default.`;
                    }
                    return "Usage: theme [dark|light|matrix|reset]";
                },
                // --- Conceptual Server-Side Commands ---
                // These would make AJAX calls to a PHP backend.
                'cache:clear': () => {
                    adminTerminalAppendToOutput("Attempting to clear server cache...");
                    // conceptualAPICall('clear_cache', {}).then(response => adminTerminalAppendToOutput(response));
                    return "Simulated: Server cache clear initiated. Check server logs.";
                },
                'logs:view': (args) => {
                    const logFile = args[1] || 'latest'; // e.g., logs:view error.log
                    adminTerminalAppendToOutput(`Fetching logs for '${logFile}'...`);
                    // conceptualAPICall('view_log', {file: logFile}).then(response => adminTerminalAppendToOutput(response));
                    return `Simulated: Displaying logs for ${logFile}. (Not implemented yet)`;
                },
                'git:status': () => {
                    adminTerminalAppendToOutput("Fetching git status...");
                    // conceptualAPICall('git_status', {}).then(response => adminTerminalAppendToOutput(response));
                    return "Simulated: Git status: On branch main, clean. (Not implemented yet)";
                }
            };

            const commandHistory = [];
            let historyIndex = -1;

            window.adminTerminalProcessKey = function(event) { // Make global for onkeydown
                if (event.key === "Enter") {
                    event.preventDefault();
                    adminTerminalProcessInput();
                } else if (event.key === "ArrowUp") {
                    event.preventDefault();
                    adminTerminalNavigateHistory(-1);
                } else if (event.key === "ArrowDown") {
                    event.preventDefault();
                    adminTerminalNavigateHistory(1);
                } else if (event.key === "Tab") {
                    event.preventDefault();
                    adminTerminalHandleTabCompletion();
                }
            }

            function adminTerminalProcessInput() {
                const userInput = adminTerminalInput.value.trim();
                if (userInput === "") return;

                adminTerminalAppendToOutput(`${adminTerminalPrompt.textContent} ${userInput}`);
                commandHistory.unshift(userInput); // Add to history
                historyIndex = -1; // Reset history index

                const args = userInput.split(" ");
                const cmd = args[0].toLowerCase();

                if (adminCommands[cmd]) {
                    const result = adminCommands[cmd](args);
                    if (result !== "") adminTerminalAppendToOutput(result);
                } else {
                    adminTerminalAppendToOutput(`Command not found: ${cmd}. Type 'help' for available commands.`);
                }
                adminTerminalInput.value = "";
                adminTerminalAutocomplete.innerHTML = "";
                adminTerminalAutocomplete.style.display = "none";
            }
            
            function adminTerminalNavigateHistory(direction) {
                if (commandHistory.length === 0) return;
                historyIndex += direction;
                if (historyIndex < 0) {
                    historyIndex = -1;
                    adminTerminalInput.value = "";
                } else if (historyIndex >= commandHistory.length) {
                    historyIndex = commandHistory.length -1; // Stay at oldest
                    adminTerminalInput.value = commandHistory[historyIndex];
                } else {
                    adminTerminalInput.value = commandHistory[historyIndex];
                }
                adminTerminalInput.setSelectionRange(adminTerminalInput.value.length, adminTerminalInput.value.length); // Move cursor to end
            }

            window.adminTerminalAutocompleteInput = function() { // Make global for oninput
                const currentInput = adminTerminalInput.value;
                if (currentInput.trim() === "") {
                    adminTerminalAutocomplete.innerHTML = "";
                    adminTerminalAutocomplete.style.display = "none";
                    return;
                }
                const lastWord = currentInput.split(" ").pop().toLowerCase();
                if (lastWord === "") {
                     adminTerminalAutocomplete.innerHTML = "";
                     adminTerminalAutocomplete.style.display = "none";
                     return;
                }

                const allCmds = Object.keys(adminCommands);
                const matchingCommands = allCmds.filter(cmd => cmd.startsWith(lastWord));

                if (matchingCommands.length > 0) {
                    adminTerminalAutocomplete.innerHTML = "";
                    matchingCommands.slice(0, 5).forEach(cmd => { // Show max 5 suggestions
                        const item = document.createElement("div");
                        item.className = "admin-autocomplete-item";
                        item.textContent = cmd;
                        item.onclick = () => {
                            const words = currentInput.split(" ");
                            words.pop();
                            words.push(cmd);
                            adminTerminalInput.value = words.join(" ") + " ";
                            adminTerminalAutocomplete.innerHTML = "";
                            adminTerminalAutocomplete.style.display = "none";
                            adminTerminalInput.focus();
                        };
                        adminTerminalAutocomplete.appendChild(item);
                    });
                    adminTerminalAutocomplete.style.display = "block";
                } else {
                    adminTerminalAutocomplete.innerHTML = "";
                    adminTerminalAutocomplete.style.display = "none";
                }
            }
            
            function adminTerminalHandleTabCompletion() {
                const suggestions = adminTerminalAutocomplete.querySelectorAll('.admin-autocomplete-item');
                if (suggestions.length === 1) { // If only one suggestion, complete it
                    suggestions[0].click();
                } else if (suggestions.length > 1) { // If multiple, show them (already shown by autocompleteInput)
                    // Could cycle through suggestions with more Tab presses, but for now just shows list
                }
            }


            window.adminTerminalFocusInput = function() { adminTerminalInput.focus(); }

            function adminTerminalAppendToOutput(text) {
                const outputLine = document.createElement("div");
                // Sanitize HTML that might come from command output if it's not trusted
                // For this simulation, assuming command output is safe or simple text
                outputLine.innerHTML = String(text).replace(/ /g, '&nbsp;'); // Preserve multiple spaces
                adminTerminalOutput.appendChild(outputLine);
                adminTerminalOutput.scrollTop = adminTerminalOutput.scrollHeight;
            }
            
            // Initial welcome message
            adminTerminalAppendToOutput("Geetanjali Admin Terminal [Version 1.0]");
            adminTerminalAppendToOutput("(c) Geetanjali Literary Society. All rights reserved.");
            adminTerminalAppendToOutput("Type 'help' for a list of available commands.");
            adminTerminalAppendToOutput("");


            // --- Window Controls (Simple Hide/Show/Conceptual Close) ---
            let isMinimized = false;
            let originalHeight = adminTerminalElement.style.height || "400px";
            let isMaximized = false;

            window.adminTerminalMinimize = function() {
                if (!isMinimized) {
                    originalHeight = adminTerminalElement.offsetHeight + "px";
                    adminTerminalElement.style.height = adminTerminalToolbar.offsetHeight + "px"; // Minimize to toolbar height
                    adminTerminalElement.style.overflow = "hidden";
                    isMinimized = true;
                } else {
                    adminTerminalElement.style.height = originalHeight;
                    adminTerminalElement.style.overflow = "auto"; // Or "hidden" then manage scroll on output
                    isMinimized = false;
                }
            }
            window.adminTerminalMaximize = function() {
                if (!isMaximized) {
                    adminTerminalElement.style.width = "95vw";
                    adminTerminalElement.style.height = "90vh";
                    adminTerminalElement.style.position = "fixed";
                    adminTerminalElement.style.left = "2.5vw";
                    adminTerminalElement.style.top = "5vh";
                    adminTerminalElement.style.zIndex = "10000";
                    isMaximized = true;
                } else {
                    adminTerminalElement.style.width = ""; // Revert to CSS defined
                    adminTerminalElement.style.height = ""; // Revert
                    adminTerminalElement.style.position = "relative"; // Revert
                    adminTerminalElement.style.left = "";
                    adminTerminalElement.style.top = "";
                    adminTerminalElement.style.zIndex = "";
                    isMaximized = false;
                }
            }
            window.adminTerminalClose = function() {
                // Instead of removing, just hide it. Can be reopened later if needed.
                adminTerminalElement.style.display = "none"; 
                // To truly remove: adminTerminalElement.remove();
            }

            // --- Dragging Functionality ---
            let isDragging = false;
            let offsetX, offsetY;

            if (adminTerminalToolbar) {
                adminTerminalToolbar.addEventListener('mousedown', (e) => {
                    // Prevent dragging if clicking on a button inside the toolbar
                    if (e.target.classList.contains('admin-window-button')) return;
                    
                    isDragging = true;
                    // Calculate offset from top-left of the terminal element
                    offsetX = e.clientX - adminTerminalElement.getBoundingClientRect().left;
                    offsetY = e.clientY - adminTerminalElement.getBoundingClientRect().top;
                    adminTerminalToolbar.style.cursor = 'grabbing';
                    adminTerminalElement.style.userSelect = 'none'; // Prevent text selection while dragging
                });
            }

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                // Position the terminal relative to the viewport
                adminTerminalElement.style.position = 'fixed'; // Ensure it's fixed for viewport positioning
                adminTerminalElement.style.left = `${e.clientX - offsetX}px`;
                adminTerminalElement.style.top = `${e.clientY - offsetY}px`;
            });

            document.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    if(adminTerminalToolbar) adminTerminalToolbar.style.cursor = 'grab';
                    adminTerminalElement.style.userSelect = '';
                }
            });

            // --- Resizing Functionality ---
            let isResizing = false;
            if (adminTerminalResizeHandle) {
                adminTerminalResizeHandle.addEventListener('mousedown', (e) => {
                    isResizing = true;
                    // No offset needed as we use clientX/Y directly for width/height
                    adminTerminalElement.style.userSelect = 'none';
                    adminTerminalElement.style.overflow = 'hidden'; // Prevent scrollbars during resize flicker
                });
            }
            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;
                const rect = adminTerminalElement.getBoundingClientRect();
                adminTerminalElement.style.width = `${e.clientX - rect.left}px`;
                adminTerminalElement.style.height = `${e.clientY - rect.top}px`;
            });
             document.addEventListener('mouseup', () => {
                if (isResizing) {
                    isResizing = false;
                    adminTerminalElement.style.userSelect = '';
                    adminTerminalElement.style.overflow = 'auto'; // Restore overflow
                }
            });
            
            // Focus input on click anywhere in terminal (except toolbar)
            adminTerminalElement.addEventListener('click', (e) => {
                if (e.target.closest('#adminTerminalToolbar')) return;
                adminTerminalInput.focus();
            });


        } // End of if (adminTerminalElement)
    }); // End of DOMContentLoaded
    </script>

    <?php include '../includes/footer.php'; ?>
    