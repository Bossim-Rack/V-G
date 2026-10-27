#Back-End :
  #1 clone du dépôt 
  git clone https://github.com/Bossim-Rack/V-G.git
  
  #2 installer les dépendances PHP 
  
  composer install
  
  #3 Creer un .env.local avec ses ID de connexion MySQL
  
  #4 Creer une base de données vide
  mysql -u root -e "CREATE DATABASE vg_database"
  
  #5 Importer la structure et les données depuis le backup
  mysql -u root vg_database < backup.sql
  
  # Lancer le serveur symfony : 
  symfony server:start 

  # Front-End :
  Serveur en local : 
  npx serve 

