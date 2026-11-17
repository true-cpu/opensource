document.addEventListener('DOMContentLoaded', () => {
  const suggestButton = document.querySelector('#suggest-button');
  if (!suggestButton) return;

  const resultBox = document.querySelector('#suggestion-box');
  suggestButton.addEventListener('click', async () => {
    const params = new URLSearchParams({
      page: 'suggestions',
      subject: document.querySelector('#subject_name').value.trim(),
      branch_id: document.querySelector('#branch_id').value,
      day: document.querySelector('#day_of_week').value,
      periods: document.querySelector('#shift_or_periods').value.trim(),
    });
    resultBox.innerHTML = '<p class="muted">Đang tìm giảng viên phù hợp...</p>';
    try {
      const response = await fetch(`index.php?${params.toString()}`, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Không thể tải gợi ý.');
      if (!data.suggestions.length) {
        resultBox.innerHTML = '<p class="muted">Không tìm thấy giảng viên còn trống trong khung tiết này.</p>';
        return;
      }
      const heading = document.createElement('p');
      heading.className = 'suggestion-heading';
      heading.textContent = 'Giảng viên có thể phân công (ưu tiên chuyên môn và cơ sở liền kề):';
      const list = document.createElement('div');
      list.className = 'suggestion-list';
      for (const teacher of data.suggestions.slice(0, 6)) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'suggestion-option';
        const title = document.createElement('strong');
        title.textContent = teacher.name;
        const details = document.createElement('small');
        details.textContent = `${teacher.expertise}${teacher.match ? ' · Đúng chuyên môn' : ''}${teacher.same_branch ? ' · Cùng cơ sở, thuận tiện di chuyển' : ''}`;
        button.append(title, details);
        button.addEventListener('click', () => {
          document.querySelector('#teacher_id').value = String(teacher.id);
          resultBox.innerHTML = '';
        });
        list.append(button);
      }
      resultBox.replaceChildren(heading, list);
    } catch (error) {
      resultBox.textContent = error.message;
    }
  });
});
