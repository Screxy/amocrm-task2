<main>
    <form id="amoForm" style="display: flex; flex-direction: column; gap:10px">
        @csrf
        <label>
            <span> Введите имя </span>
            <input type="text" name="first_name" id="first_name">
        </label>
        <label>
            <span> Введите фамилию </span>
            <input type="text" name="last_name" id="last_name">
        </label>
        <label>
            <span> Введите возраст </span>
            <input type="number" name="age" id="age">
        </label>
        <label>
            <span> Выберите пол </span>
            <select name="gender" id="gender">
                <option value="Мужской">Мужской</option>
                <option value="Женский">Женский</option>
            </select>
        </label>
        <label>
            <span> Введите телефон </span>
            <input type="tel" name="phone" id="phone">
        </label>
        <label>
            <span> Введите email </span>
            <input type="email" name="email" id="email">
        </label>

        <button type="button" style="max-width: 200px;" onclick="prepareAndSend()">Отправить</button>
    </form>
    
    <script>
        function prepareAndSend() {
            var formData = {
                'first_name': document.getElementById('first_name').value,
                'last_name': document.getElementById('last_name').value,
                'age': document.getElementById('age').value,
                'gender': document.getElementById('gender').value,
                'phone': document.getElementById('phone').value,
                'email': document.getElementById('email').value,
            };
            var jsonData = JSON.stringify(formData);
            console.log(jsonData);
            fetch('http://127.0.0.1:8000/api/amo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value
                    },
                    body: jsonData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Успешно отправлено:', data);
                })
        }
    </script>
</main>
