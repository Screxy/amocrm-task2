<main>
    <form action="/api/amo" method="POST" style="display: flex; flex-direction: column; gap:10px">
        @csrf
        <label>
            <span> Введите имя </span>
            <input type="text" name="first_name">
        </label>
        <label>
            <span> Введите фамилию </span>
            <input type="text" name="last_name">
        </label>
        <label>
            <span> Введите возраст </span>
            <input type="number" name="age">
        </label>
        <label>
            <span> Выберите пол </span>
            <select name="gender">
                <option value="male">male</option>
                <option value="female">female</option>
            </select>
        </label>
        <label>
            <span> Введите телефон </span>
            <input type="tel" name="phone">
        </label>
        </label>
        <label>
            <span> Введите email </span>
            <input type="email" name="email">
        </label>
        <button style="max-width: 200px;">Отправить</button>
    </form>
</main>
