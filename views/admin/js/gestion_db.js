// AscendForm - JavaScript pour gestion_db.php

function editRow(rowId) {
    const row = document.getElementById('row-' + rowId);
    row.querySelectorAll('.view-mode').forEach(el => el.classList.add('d-none'));
    row.querySelectorAll('.edit-mode').forEach(el => el.classList.remove('d-none'));
    row.querySelector('.edit-btn').classList.add('d-none');
    row.querySelector('.save-btn').classList.remove('d-none');
    row.querySelector('.cancel-btn').classList.remove('d-none');
    row.querySelector('.delete-btn').classList.add('d-none');
}

function cancelEdit(rowId) {
    const row = document.getElementById('row-' + rowId);
    row.querySelectorAll('.view-mode').forEach(el => el.classList.remove('d-none'));
    row.querySelectorAll('.edit-mode').forEach(el => el.classList.add('d-none'));
    row.querySelector('.edit-btn').classList.remove('d-none');
    row.querySelector('.save-btn').classList.add('d-none');
    row.querySelector('.cancel-btn').classList.add('d-none');
    row.querySelector('.delete-btn').classList.remove('d-none');
}

function saveRow(rowId, table) {
    const row = document.getElementById('row-' + rowId);
    const updates = {};
    
    row.querySelectorAll('.edit-mode').forEach(input => {
        const column = input.dataset.column;
        updates[column] = input.value;
    });
    
    const formData = new FormData();
    formData.append('action', 'edit_row');
    formData.append('target_db', selectedDb);
    formData.append('table', table);
    formData.append('row_id', rowId);
    
    for (const [key, value] of Object.entries(updates)) {
        formData.append(`updates[${key}]`, value);
    }
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        location.reload();
    })
    .catch(error => {
        alert('Erreur lors de la sauvegarde: ' + error);
    });
}
