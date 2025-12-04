# 📚 Analyseur de PDF - Fréquence des Mots

Un analyseur de documents PDF élégant avec une interface inspirée des vieilles bibliothèques et manuscrits anciens. Cet outil permet d'extraire et d'analyser la fréquence des mots contenus dans vos documents PDF.

![Interface Vintage](https://github.com/sbois/Analyseur-de-PDF---Nombre-d-occurences-d-un-mot/blob/main/capture.png)

## ✨ Fonctionnalités

- 📄 **Extraction de texte** : Analyse complète des fichiers PDF via Poppler (pdftotext)
- 🔤 **Analyse linguistique** : 
  - Normalisation UTF-8 pour gérer les caractères accentués
  - Tokenisation intelligente des mots
  - Filtrage automatique des stopwords (articles, pronoms, prépositions)
- 📊 **Statistiques détaillées** : Comptage des occurrences de chaque mot
- 🔍 **Recherche instantanée** : Trouvez rapidement la fréquence d'un mot spécifique
- 💾 **Export CSV** : Téléchargez vos résultats au format CSV
- 🎨 **Interface vintage** : Design inspiré des vieilles bibliothèques avec ambiance musicale
- 💾 **Session persistante** : Les résultats restent disponibles entre les recherches

## 🛠️ Prérequis

### Serveur
- PHP 7.4 ou supérieur
- Extensions PHP requises :
  - `mbstring` (gestion des chaînes multibytes UTF-8)
  - `intl` (normalisation Unicode - optionnel mais recommandé)

### Outils système
- **Poppler Utils** : Pour l'extraction de texte PDF
  - Linux : `sudo apt-get install poppler-utils`
  - macOS : `brew install poppler`
  - Windows : [Télécharger Poppler](https://github.com/oschwartz10612/poppler-windows/releases/)

## 📦 Installation

1. **Cloner le repository**
```bash
git clone https://github.com/votre-username/analyseur-pdf-vintage.git
cd analyseur-pdf-vintage
```

2. **Vérifier les dépendances**
```bash
# Vérifier que pdftotext est installé
pdftotext -v

# Vérifier les extensions PHP
php -m | grep mbstring
php -m | grep intl
```

3. **Configuration du serveur**
```bash
# Créer le dossier uploads avec les bonnes permissions
mkdir uploads
chmod 777 uploads
```

4. **Lancer le serveur local**
```bash
# Avec PHP built-in server
php -S localhost:8000

# Ou avec XAMPP/WAMP/MAMP
# Placer les fichiers dans le dossier htdocs/www
```

5. **Accéder à l'application**
```
http://localhost:8000/index.php
```

## 🎯 Utilisation

### 1. Analyser un PDF
- Cliquez sur "Choisissez un fichier PDF"
- Sélectionnez votre document
- Cliquez sur "Analyser le PDF"
- Attendez quelques secondes pendant l'analyse

### 2. Consulter les résultats
- Visualisez le tableau complet des mots et leurs occurrences
- Les mots sont triés par ordre décroissant de fréquence
- Le nombre total de mots (hors stopwords) est affiché

### 3. Rechercher un mot spécifique
- Utilisez le formulaire de recherche
- Entrez le mot souhaité
- Obtenez instantanément son nombre d'occurrences

### 4. Exporter les données
- Cliquez sur "📥 Télécharger en CSV"
- Le fichier contient : numéro, mot, occurrence

### 5. Réinitialiser
- Cliquez sur "Remise à zéro" pour effacer la session
- Analysez un nouveau document

## 🎨 Personnalisation

### Modifier l'apparence
Le fichier utilise des polices Google Fonts :
- **Cinzel** : Titres élégants
- **Crimson Text** : Corps de texte lisible

### Changer l'image de fond
```css
background: url('VOTRE_URL_IMAGE') center/cover fixed;
```

### Ajouter une musique d'ambiance
Le code inclut un lecteur audio vintage. Personnalisez :
```html
<source src="votre-fichier-audio.mp3" type="audio/mpeg">
```

### Modifier les stopwords
Dans la fonction `analysePDF()`, ajoutez ou retirez des mots du tableau `$stopwords`.

## 📁 Structure du projet

```
analyseur-pdf-vintage/
│
├── index.php              # Fichier principal
├── uploads/               # Dossier des PDF téléversés
├── README.md             # Ce fichier
└── assets/               # (Optionnel) Images, sons, etc.
```

## 🔧 Fonctions principales

### `normalizeToLowerUtf8(string $text): string`
Normalise le texte en UTF-8 et le convertit en minuscules.

### `extractTextWithPoppler(string $pdfPath): string`
Extrait le texte d'un PDF via pdftotext (Poppler).

### `analysePDF(string $filePath): array`
Fonction principale qui :
1. Extrait le texte
2. Normalise et nettoie
3. Tokenise en mots
4. Filtre les stopwords
5. Compte les occurrences

## ⚙️ Configuration avancée

### Augmenter la limite de téléversement
Dans `php.ini` :
```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
```

### Personnaliser pdftotext
Modifier la commande dans `extractTextWithPoppler()` :
```php
$cmd = 'pdftotext -layout -enc UTF-8 ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($outputTxt);
```

Options utiles :
- `-layout` : Préserve la mise en page
- `-enc UTF-8` : Encodage UTF-8
- `-nopgbrk` : Pas de saut de page

## 🐛 Dépannage

### Erreur "pdftotext command not found"
- Installez Poppler Utils (voir Prérequis)
- Vérifiez le PATH système

### Erreur "Undefined variable"
- Vérifiez que le code PHP n'a pas été modifié
- Assurez-vous d'utiliser PHP 7.4+

### PDF non analysé
- Vérifiez que le PDF contient du texte (pas juste des images)
- Essayez avec l'option `-raw` dans pdftotext
- Certains PDF protégés peuvent poser problème

### Permissions uploads/
```bash
chmod 755 uploads/
# ou
chmod 777 uploads/  # Si nécessaire
```

## 🌍 Support linguistique

Actuellement optimisé pour le **français** avec :
- Stopwords français complets (surement qu'il en manque 😅)
- Gestion des accents (é, è, à, ç, etc.)
- Support Unicode complet

Pour d'autres langues, modifiez le tableau `$stopwords` dans `analysePDF()`.

## 📝 Licence

Ce projet est sous GNU GPLv3. Vous êtes libre de l'utiliser, le modifier et le distribuer.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :
- Signaler des bugs
- Proposer des améliorations
- Ajouter des fonctionnalités
- Améliorer la documentation

## 👨‍💻 Auteur

Créé avec ❤️ à l'aide de ClaudeIA pour l'analyse de documents

## 🙏 Remerciements

- **Poppler** : Pour l'excellente bibliothèque d'extraction PDF
- **Unsplash** : Pour les magnifiques images de bibliothèques
- **Google Fonts** : Pour les polices Cinzel et Crimson Text

---

⭐ Si ce projet vous plaît, n'oubliez pas de lui donner une étoile sur GitHub !
