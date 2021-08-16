<head>
  <!-- Font imports -->
  <link href = "https://fonts.googleapis.com/css?family=Montserrat" rel = "stylesheet">

  <!-- Styling -->
  <style>
    #pg_contact-form form {
      display: flex;
      flex-direction: column;
      width: 100%;
    }

    #pg_contact-form label {
      margin-bottom: 30px;
    }

    #pg_contact-form textarea {
      resize: vertical;
    }

    #pg_contact-form label {
      max-width: 700px;
    }

    #pg_contact-form input, #pg_contact-form textarea {
      border-radius: 5px;
      padding: 11px 15px;
      font-family: "Montserrat", sans-serif;
    }

    #pg_contact-form #submit {
      max-width: 150px;
    }

    #pg_contact-form #submit input {
      width: 100%;
      border: 1px #285380 solid;
      color: #285380;
      padding: 15px 0;
      cursor: pointer;
      background: none;
      transition: color .5s ease, background .5s ease;
      border-radius: 0;
    }

    #pg_contact-form #submit input:hover {
      color: white;
      background: #285380;
    }

    #pg_contact-form input:focus, #pg_contact-form textarea:focus {
      box-shadow: 0 0 8px 1px rgba(40, 83, 128, 0.5) inset;
    }
  </style>
</head>

<!-- Content -->
<div id = "pg_contact-form">
  <form method = "post">
    <!-- Name (or username) input -->
    <label id = "name">
      <input type = "text" placeholder = "Name or Username">
    </label>

    <!-- Email input -->
    <label id = "email">
      <input type = "text" placeholder = "Your Email Address">
    </label>

    <!-- Message input -->
    <label id = "message">
      <textarea placeholder = "Message"></textarea>
    </label>

    <!-- Submit -->
    <label id = "submit">
      <input type = "submit">
    </label>
  </form>
</div>