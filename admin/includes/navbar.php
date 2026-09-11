<header class="topbar">

    <button id="toggleSidebar" class="toggle-btn">

        <i class="fa-solid fa-bars"></i>

    </button>

    <div class="search-box">

        <i class="fa-solid fa-magnifying-glass"></i>

        <input type="text" placeholder="Search anything...">

    </div>

    <div class="top-right">

        <button class="notification">

            <i class="fa-regular fa-bell"></i>

            <span class="badge">3</span>

        </button>

        <button class="dark-btn">

            <i class="fa-solid fa-moon"></i>

            Dark Mode

        </button>

        <div class="profile">

            <div class="avatar">

                <?php echo strtoupper(substr($_SESSION['user_name'],0,2)); ?>

            </div>

            <div class="profile-text">

                <h5><?php echo $_SESSION['user_name']; ?></h5>

                <span>Administrator</span>

            </div>

        </div>

    </div>

</header>
