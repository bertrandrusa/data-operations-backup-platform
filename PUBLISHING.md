# Publish this repository on GitHub

## 1. Create the remote repository

While signed in as `bertrandrusa`, open [GitHub's new repository page](https://github.com/new) and use:

- **Repository name:** `data-operations-backup-platform`
- **Description:** `Containerized data operations platform with authenticated backup control, PostgreSQL audit history, incremental rsync snapshots, and verified recovery workflows.`
- **Visibility:** Public (recommended for a portfolio project)
- Leave **README**, **.gitignore**, and **license** unchecked because they are already included here.

## 2. Push the project

From the extracted project directory:

```bash
git init
git branch -M main
git add .
git commit -m "Build data operations and backup platform"
git remote add origin https://github.com/bertrandrusa/data-operations-backup-platform.git
git push -u origin main
```

In PowerShell, the commands are the same. GitHub may open a browser for authentication.

## 3. Finish the repository profile

In the repository's **About** settings, add the description above and these topics:

```text
docker postgresql php apache rsync backup recovery data-operations linux cybersecurity devops
```

After the first push, wait for the **CI** workflow to finish. A green run confirms PHP syntax/tests, ShellCheck, Compose validation, and container builds in a Docker-enabled environment.
