SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


CREATE TABLE compte (
    id_compte INT AUTO_INCREMENT PRIMARY KEY,
    mail VARCHAR(255) NOT NULL UNIQUE,
    mdp VARCHAR(255) NOT NULL,
    ip VARCHAR(45),
    derniere_connection DATE
) ENGINE=InnoDB;


CREATE TABLE client (
    id_client INT PRIMARY KEY,
    telephone VARCHAR(20),
    taille DOUBLE,
    poids DOUBLE,
    nom_client VARCHAR(100) NOT NULL,
    prenom_client VARCHAR(100) NOT NULL,
    date_anniv DATE,
    age INT,
    CONSTRAINT fk_client_compte
        FOREIGN KEY (id_client)
        REFERENCES compte(id_compte)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE admin (
    id_admin INT PRIMARY KEY,
    CONSTRAINT fk_admin_compte
        FOREIGN KEY (id_admin)
        REFERENCES compte(id_compte)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE message (
    id_message INT AUTO_INCREMENT PRIMARY KEY,
    sujet VARCHAR(255),
    status VARCHAR(50),
    repondu_le DATE,
    id_compte INT,
    id_reponse INT,
    CONSTRAINT fk_message_compte
        FOREIGN KEY (id_compte)
        REFERENCES compte(id_compte)
        ON DELETE CASCADE,
    CONSTRAINT fk_message_reponse
        FOREIGN KEY (id_reponse)
        REFERENCES message(id_message)
) ENGINE=InnoDB;


CREATE TABLE logs (
    id_logs INT AUTO_INCREMENT PRIMARY KEY,
    cree_le DATE,
    mis_a_jour_le DATE,
    id_compte INT,
    CONSTRAINT fk_logs_compte
        FOREIGN KEY (id_compte)
        REFERENCES compte(id_compte)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE ip_bloque (
    id_ip_bloque INT AUTO_INCREMENT PRIMARY KEY,
    bloque_jusqu_a DATE,
    raison VARCHAR(255),
    id_logs INT,
    id_compte INT,
    CONSTRAINT fk_ip_logs
        FOREIGN KEY (id_logs)
        REFERENCES logs(id_logs)
        ON DELETE CASCADE,
    CONSTRAINT fk_ip_compte
        FOREIGN KEY (id_compte)
        REFERENCES compte(id_compte)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE exercice (
    id_exo INT AUTO_INCREMENT PRIMARY KEY,
    nom_exo VARCHAR(255),
    muscle_cible VARCHAR(255),
    url_video VARCHAR(255)
) ENGINE=InnoDB;


CREATE TABLE client_exercice (
    id_client INT,
    id_exo INT,
    PRIMARY KEY (id_client, id_exo),
    CONSTRAINT fk_ce_client
        FOREIGN KEY (id_client)
        REFERENCES client(id_client)
        ON DELETE CASCADE,
    CONSTRAINT fk_ce_exercice
        FOREIGN KEY (id_exo)
        REFERENCES exercice(id_exo)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE video (
    id_video INT AUTO_INCREMENT PRIMARY KEY,
    url_video VARCHAR(255),
    titre_video VARCHAR(255),
    description_video TEXT,
    id_exo INT UNIQUE,
    CONSTRAINT fk_video_exercice
        FOREIGN KEY (id_exo)
        REFERENCES exercice(id_exo)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE photo (
    id_photo INT AUTO_INCREMENT PRIMARY KEY,
    chemin_photo VARCHAR(255),
    titre_photo VARCHAR(255),
    description_photo TEXT,
    id_exo INT UNIQUE,
    CONSTRAINT fk_photo_exercice
        FOREIGN KEY (id_exo)
        REFERENCES exercice(id_exo)
        ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
