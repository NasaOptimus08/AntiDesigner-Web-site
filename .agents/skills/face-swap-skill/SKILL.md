---
name: face-swap
description: "Незаметная замена лица на фотографии через нейросеть (Nano Banana / Gemini и подобные): лицо перерисовывается внутри сцены, а не вклеивается пикселями, и подгоняется под свет и цвет кадра. Использовать, когда нужно поставить лицо конкретного человека (себя, клиента, его команды) в готовый кадр или сгенерированное изображение."
---

# Незаметная замена лица

**Обновлено: 07.08.2026**

Скилл лечит две болезни обычной замены лица:

1. **«Вклеенная фотка»** — модель копирует пиксели исходного селфи вместо того, чтобы
   нарисовать лицо заново внутри сцены.
2. **«Голова другого цвета»** — на лицо переносится свет и оттенок кожи с селфи, а не
   освещение сцены, и голова выглядит инородной.

## Что подаём на вход

- **Первое фото** — сцена, куда вставляем лицо.
- **Второе фото** — портрет человека, чьё лицо нужно получить.

Портрет подбирать **по ракурсу** под позу в сцене: анфас, профиль, три четверти, взгляд
вверх или вниз. Совпал ракурс — сходство будет заметно лучше. Зеркальность (профиль влево
или вправо) роли не играет.

Важно: делать в два прохода (сначала вставить лицо, потом отдельно перекрасить) **не
работает**. Всё должно быть в одном промте.

## Структура промта

**1. Сцена.** Подробно опишите первый кадр и стиль съёмки (плёнка, студия, вспышка,
дневной свет). Если нужно убрать лишних людей или надписи, скажите об этом и укажите, чем
заполнить освободившееся место.

**2. Идентичность.** Дословно, эти фразы работают:

```
The second photo is ONLY an identity reference for facial features and haircut, nothing else.
Completely ignore and discard its lighting, colors, white balance and skin tone: the second photo was taken in different light that must not appear in the result.
Re-render the face from scratch as part of this scene: [ОПИСАНИЕ ВНЕШНОСТИ], with a natural expression looking [НАПРАВЛЕНИЕ ВЗГЛЯДА].
```

**3. Цвет — главный блок, не сокращать.** Эталон тона берём с кожи, которая уже видна в
кадре (руки, шея, плечи):

```
Color is critical: the head must be rendered in the exact color environment of the first image.
The skin tone of the face, ears and neck must exactly match the skin tone of the person's hands and arms in the first image.
White balance comes from the light source of the scene ([ИСТОЧНИК СВЕТА: окно / студийная панель / вспышка]).
No warm, pink or orange tint on the head.
The light from [ИСТОЧНИК] shapes the shadows on the face: gentle shadows under the brows, nose and chin.
Same grain and softness as the rest of the frame.
```

**4. Против эффекта коллажа.** Финальная фраза:

```
One single photograph shot in one moment on one camera: the head, the body and the scene share identical lighting, color and grain, nothing looks pasted in.
```

**5. Формат.** Если референс вертикальный, а нужен горизонтальный:

```
Extend the scene naturally to the sides, wide horizontal 16:9 format, no text or watermarks anywhere.
```

## Как описывать внешность

Опишите словами то, что видно на портрете: возраст, цвет глаз, форма бровей, борода и
щетина, тон кожи, стрижка и цвет волос, очки. Пример структуры:

```
a man around 30 years old, blue-gray eyes, neatly trimmed light-brown beard and stubble, fair skin, short textured light-brown hair with a skin fade on the sides, no glasses
```

Стрижку берите с портрета, а не со сцены: причёска сильно влияет на узнаваемость. Кепки и
очки персонажа из сцены по умолчанию убирайте (`no cap: his own bare head`).

## Полезные добавки

- **Комплекция.** Если фигура в сцене не подходит:
  `slightly bigger and stockier: broader shoulders, fuller arms and chest, not muscular exaggeration`.
- **Реализм** (особенно если сцена нарисованная):
  `Strictly photorealistic: not an illustration, not a painting, no airbrushed skin, natural skin texture with pores`.
- **Выражение лица** можно задать словами: положение бровей, губ, морщины на лбу.
- **Если лицо легло органично, но сходство слабое** — второй проход: подайте результат
  вместе с портретом и попросите
  `subtly adjust the facial features to better match the reference person, without changing lighting, grain or color grading`.

## Порядок работы

1. Подберите портрет по ракурсу под сцену.
2. Соберите промт по блокам выше и сгенерируйте несколько вариантов.
3. Выберите тот, где лицо ближе всего и цвет совпадает со сценой.
4. При необходимости поднимите качество апскейлером (Magnific, Hixfield и подобные).
5. Финальную доводку делайте в Photoshop: наложите выбранный кадр на оригинал, выровняйте
   размер головы, снизьте прозрачность для точного совмещения и мягко сотрите края.

## Чего ожидать

С первого раза идеально не выйдет — это нормально, генерируйте несколько вариантов. Скилл
убирает грубые провалы: вклеенные головы, чужой цвет кожи, потерянное направление взгляда.
Если лицо на итоговой картинке будет некрупно, достаточно узнаваемого сходства.
