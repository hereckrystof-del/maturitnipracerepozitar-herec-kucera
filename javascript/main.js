//ovladani menu
const navMenu = document.getElementById('nav-menu');
const navToggle = document.getElementById('nav-toggle');
const navClose = document.getElementById('nav-close');

//menu na mobilu
if (navToggle) {
    navToggle.addEventListener('click', () => navMenu.classList.add('show-menu'));
}

//skryt menu
if (navClose) {
    navClose.addEventListener('click', () => navMenu.classList.remove('show-menu'));
}

//modalni okna
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return; //kdyby to nefungovalo

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    
    //hledani username pri loginu
    if (id === 'loginModal') {
        const usernameInput = document.getElementById('username');
        if (usernameInput) usernameInput.focus();
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    if (id === 'unifiedDeleteModal') {
        const returnId = modal.dataset.returnModal;
        if (returnId) {
            openModal(returnId);
            modal.dataset.returnModal = ''; //vycisti se to(melo by)
        }
    }

    // Pokud zavíráme detail článku, vrátíme URL zpět na záložku (odstraníme ID)
    if (id === 'detailModal' && typeof ROUTE_TAB !== 'undefined') {
        history.pushState({}, '', '/' + ROUTE_TAB);
    }
}


//formulare
//data
function openEditPostModal(post) {
    //nacte info z postu
    document.getElementById('edit_post_id').value = post.id;
    document.getElementById('edit_post_title').value = post.title;
    document.getElementById('edit_post_date').value = post.datum;
    document.getElementById('edit_post_date_do').value = post.datum_do || '';
    document.getElementById('edit_post_link').value = post.odkaz || '';
    document.getElementById('edit_post_content').value = post.content;

    //renderovani fotek pro smazani
    const container = document.getElementById('existingImagesContainer');
    container.innerHTML = '';

    if (post.images && post.images.length > 0) {
        post.images.forEach((imgSrc, index) => {
            const imgDiv = document.createElement('div');
            imgDiv.className = 'image-item-edit';
            imgDiv.innerHTML = `
                <label>
                    <img src="${imgSrc}" alt="Fotka ${index + 1}">
                    <input type="checkbox" name="remove_image[]" value="${imgSrc}">
                </label>
            `;
            container.appendChild(imgDiv);
        });

        const deleteBtn = document.getElementById('deleteSelectedImagesBtn');
        if(deleteBtn) deleteBtn.classList.add('show');
    } else {
        container.innerHTML = '<p class="no-images-hint">Zatím nejsou přiloženy žádné fotky.</p>';
        const deleteBtn = document.getElementById('deleteSelectedImagesBtn');
        if(deleteBtn) deleteBtn.classList.remove('show');
    }

    openModal('editPostModal');
}

// TODO: mozna predelat pres fetch API, ale zatim staci takto pres form.submit()
function submitDeleteImages(e) {
    e.preventDefault();
    const checkboxes = document.querySelectorAll('#existingImagesContainer input[name="remove_image[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('Nejdřív vyber nějaké fotky.');
        return;
    }
    if (!confirm('Opravdu to chceš smazat?')) return;

    //form a poslat ho
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'actions/delete_post_images.php';
    
    const postIdInput = document.createElement('input');
    postIdInput.type = 'hidden';
    postIdInput.name = 'post_id';
    postIdInput.value = document.getElementById('edit_post_id').value;
    form.appendChild(postIdInput);
    
    checkboxes.forEach(cb => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_image[]';
        hiddenInput.value = cb.value;
        form.appendChild(hiddenInput);
    });
    
    document.body.appendChild(form);
    form.submit();
}

function openEditNewsModal(news) {
    document.getElementById('edit_news_id').value = news.id || '';
    document.getElementById('edit_news_title').value = news.title || '';
    document.getElementById('edit_news_date').value = news.datum || '';
    document.getElementById('edit_news_date_do').value = news.datum_do || '';
    document.getElementById('edit_news_link').value = news.odkaz || '';
    document.getElementById('edit_news_content').value = news.content || '';
    openModal('editNewsModal');
}

function openEditMemberModal(member) {
    if (!member) return;
    document.getElementById('edit_member_id').value = member.id || '';
    document.getElementById('edit_member_name').value = member.jmeno || '';
    document.getElementById('edit_member_position').value = member.pozice || '';
    document.getElementById('edit_member_email').value = member.email || '';
    document.getElementById('edit_member_phone').value = member.telefon || '';
    document.getElementById('edit_member_description').value = member.popis || '';
    openModal('editMemberModal');
}

function openEditBanerModal(baner) {
    document.getElementById('edit_baner_id').value = baner.id;
    document.getElementById('edit_baner_content').value = baner.content;
    openModal('editBanerModal');
}


//detaily
//clenove a posty

function openMemberDetailModal(member) {
    document.getElementById('memberDetailName').innerText = member.jmeno;
    document.getElementById('memberDetailPosition').innerText = member.pozice;
    
    const photoContainer = document.getElementById('memberDetailPhoto');

    //jestli nema fotku nebo ma default.jpg -> inicial
    if (member.fotografie && member.fotografie.trim() !== '' && member.fotografie.trim() !== 'default.jpg') {
        photoContainer.innerHTML = '<img src="uploads/Clenove/' + encodeURIComponent(member.fotografie) + '" alt="' + escapeHtml(member.jmeno) + '">';
    } else {
        photoContainer.innerHTML = '<div class="member-avatar">' + member.jmeno.charAt(0).toUpperCase() + '</div>';
    }
    
    let infoHtml = '';
    if (member.email && member.email.trim() !== '') {
        infoHtml += `<p><strong>E-mail:</strong> <a href="mailto:${escapeHtml(member.email)}">${escapeHtml(member.email)}</a></p>`;
    }
    if (member.telefon && member.telefon.trim() !== '') {
        infoHtml += `<p><strong>Telefon:</strong> <a href="tel:${escapeHtml(member.telefon)}">${escapeHtml(member.telefon)}</a></p>`;
    }
    if (member.popis && member.popis.trim() !== '') {
        infoHtml += `<p class="member-detail-description">${nl2br(escapeHtml(member.popis))}</p>`;
    }
    
    document.getElementById('memberDetailInfo').innerHTML = infoHtml || '<p class="member-detail-empty">Zatím bez informací.</p>';
    openModal('memberDetailModal');
}


let detailImages = [];
let currentDetailImgIndex = 0;

function parseLinks(text) {
    //nejdriv escapujeme HTML aby nebyl XSS problem
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    //prevod novych radku
    const withBr = escaped.replace(/\n/g, '<br>');
    return withBr.replace(
        /\[([^\]]{1,200})\]\((https?:\/\/[^)]{1,500})\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer" class="content-link">$1</a>'
    );
}

function openDetailModal(data) {
    console.log("Načtená data z databáze:", data); // Pomůcka: ukáže data v konzoli prohlížeče (F12)

    const titleEl = document.getElementById('detailTitle');
    if(titleEl) titleEl.innerText = data.title;
    
    //datum od - do
    const dateEl = document.getElementById('detailDate');
    if(dateEl) {
        if (data.datum) {
            const dateObj = new Date(data.datum);
            dateEl.innerText = dateObj.toLocaleDateString('cs-CZ');
            if(data.datum_do) {
                const dateObjDo = new Date(data.datum_do);
                dateEl.innerText += ' – ' + dateObjDo.toLocaleDateString('cs-CZ');
            }
        } else {
             dateEl.innerText = '';
        }
    }

    const contentEl = document.getElementById('detailContent');
    if(contentEl) contentEl.innerHTML = parseLinks(data.content || '');

    //nacitani neomezeného počtu fotek
    detailImages = data.images || [];
    const imageContainer = document.getElementById('detailImageContainer');
    
    if (imageContainer) {
        imageContainer.innerHTML = ''; // Vyčistíme staré fotky
        if (detailImages.length > 0) {
            imageContainer.classList.add('has-images');
            detailImages.forEach((src, idx) => {
                const imgEl = document.createElement('img');
                imgEl.src = src;
                imgEl.alt = 'Fotka ' + (idx + 1);
                imgEl.className = 'detail-thumb';
                imgEl.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openDetailLightbox(idx);
                });
                imageContainer.appendChild(imgEl);
            });
        } else {
            imageContainer.classList.remove('has-images');
        }
    }

    const linkContainer = document.getElementById('detailLinkContainer');
    const linkEl = document.getElementById('detailLink');
    if(linkContainer && linkEl) {
        if (data.odkaz && data.odkaz.trim() !== "") {
            linkEl.href = data.odkaz;
            linkContainer.classList.add('visible');
        } else {
            linkContainer.classList.remove('visible');
        }
    }

    // ZOBRAZENÍ AUTORA
    const authorEl = document.getElementById('detailAuthor');
    if (authorEl) {
        if (data.autor && data.autor.trim() !== "") {
            authorEl.innerText = "Autor: " + data.autor;
            authorEl.style.display = "block";
        } else {
            authorEl.style.display = "none";
        }
    }

    openModal('detailModal');

    //zmena url pro sdileni
    if (typeof ROUTE_TAB !== 'undefined' && data && data.id) {
        history.pushState({ modalOpen: true }, '', '/' + ROUTE_TAB + '/' + data.id);
    }
}


//zvetsovani fotek (lihgtbox)

function openDetailLightbox(index) {
    if (!detailImages || detailImages.length === 0) return;
    currentDetailImgIndex = index;
    document.getElementById('detailLightbox-img').src = detailImages[currentDetailImgIndex];
    document.getElementById('detailLightbox-counter').innerText = `${currentDetailImgIndex + 1} / ${detailImages.length}`;
    document.getElementById('detailLightbox').classList.add('show');
    document.body.style.overflow = 'hidden'; //aby se to na pozadi nescrollovalo
}

function closeDetailLightbox() {
    document.getElementById('detailLightbox').classList.remove('show');
    document.body.style.overflow = 'auto';
}

function changeDetailImage(step) {
    if (!detailImages || detailImages.length === 0) return;
    //tricek na zacykleni indexu aby to neslo do minusu
    currentDetailImgIndex = (currentDetailImgIndex + step + detailImages.length) % detailImages.length;
    document.getElementById('detailLightbox-img').src = detailImages[currentDetailImgIndex];
    document.getElementById('detailLightbox-counter').innerText = `${currentDetailImgIndex + 1} / ${detailImages.length}`;
}

//globalni promenne pro hlavni galerii
let currentImgIndex = 0; 
let images = [];

function openLightbox(index) {
    //najde si vsechny img tagy aktualne zobrazeny na strance
    images = Array.from(document.querySelectorAll('.gallery-img')).map(img => img.src);
    currentImgIndex = index;
    
    document.getElementById('lightbox-img').src = images[currentImgIndex];
    document.getElementById('lightbox-counter').innerText = `${currentImgIndex + 1} / ${images.length}`;
    document.getElementById('lightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
    document.body.style.overflow = 'auto';
    
    const counter = document.getElementById('lightbox-counter');
    if (counter) counter.style.display = 'block';
    document.querySelectorAll('.lightbox .nav-arrow').forEach(el => el.style.display = 'block');
}

function changeImage(step) {
    if (images.length === 0) return;
    currentImgIndex = (currentImgIndex + step + images.length) % images.length;
    document.getElementById('lightbox-img').src = images[currentImgIndex];
    document.getElementById('lightbox-counter').innerText = `${currentImgIndex + 1} / ${images.length}`;
}

//bannery
function openSingleLightbox(src) {
    images = [src];
    currentImgIndex = 0;
    document.getElementById('lightbox-img').src = src;
    const counter = document.getElementById('lightbox-counter');
    if (counter) counter.style.display = 'none';
    document.querySelectorAll('.lightbox .nav-arrow').forEach(el => el.style.display = 'none');
    
    document.getElementById('lightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}

//UI

//zobrazeni/skryti hesla
function setupPasswordToggle(inputId, buttonId) {
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);
    if (!input || !button) return;
    
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.textContent = isPassword ? 'Ukázat' : 'Skrýt';
    });
}

setupPasswordToggle('passwordInput', 'togglePassword');
setupPasswordToggle('newPasswordInput', 'toggleNewPassword');


//scroll tlacitko nahoru
window.addEventListener('scroll', () => {
    const btn = document.getElementById('scrollToTopBtn');
    if (btn) btn.classList.toggle('show', window.scrollY > 300);
});


// Univerzální funkce pro máslově plynulé scrollování
function customSmoothScrollTo(targetY, duration = 800) {
    const startY = window.scrollY;
    const difference = targetY - startY;
    const startTime = performance.now();

    function step(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Křivka animace (ease-out-cubic) - na začátku zrychlí, u cíle elegantně zpomalí
        const easeProgress = 1 - Math.pow(1 - progress, 3);
        
        window.scrollTo(0, startY + difference * easeProgress);
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    }
    
    window.requestAnimationFrame(step);
}

// Funkce pro tlačítko v Hero sekci (dolů na obsah)
function scrollToTabs() {
    const mainContainer = document.querySelector('main.container');
    const header = document.getElementById('header');
    
    if (mainContainer) {
        const headerHeight = header ? header.offsetHeight : 105;
        // Přesný výpočet cílové pozice (od vrchu stránky minus výška menu)
        const targetY = mainContainer.getBoundingClientRect().top + window.scrollY - headerHeight;
        
        // Spustíme náš plynulý scroll (800 milisekund)
        customSmoothScrollTo(targetY, 800);
    }
}

// Funkce pro tlačítko v patičce (nahoru)
function scrollToTop() {
    // Spustíme plynulý scroll úplně nahoru (na pixel 0)
    customSmoothScrollTo(0, 800);
}


//drag and drop pro fotky
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('gallery-upload-section'); // Změněno na celou sekci
    const fileInput = document.getElementById('fotka-input');
    const dropZoneText = document.getElementById('drop-zone-text');

    if (dropZone && fileInput) {
        //zrusi defaultni chovani chromu, aby se neotevrel ten soubor
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }, false);
        });

        //pridani/odebrani stridani pro styling
        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, () => dropZone.classList.add('dragover'), false);
        });
        
        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', e => { 
            fileInput.files = e.dataTransfer.files; 
            updateFileName(); 
        }, false);
        
        fileInput.addEventListener('change', updateFileName);
    }

    function updateFileName() {
        if (fileInput.files.length > 0) {
            const count = fileInput.files.length;
            dropZoneText.innerText = count === 1 ? `Vybrán 1 soubor: ${fileInput.files[0].name}` : `Vybráno ${count} souborů`;
            dropZoneText.classList.add('drop-zone-text--active');
            dropZoneText.classList.remove('drop-zone-text--idle');
        } else {
            dropZoneText.innerText = 'Vybrat fotky z počítače';
            dropZoneText.classList.add('drop-zone-text--idle');
            dropZoneText.classList.remove('drop-zone-text--active');
        }
    }
});


//ovladani klavesnici

//escape a sipky v lightboxech
document.addEventListener('keydown', function(e) {
    const detailLightbox = document.getElementById('detailLightbox');
    const galleryLightbox = document.getElementById('lightbox');

    //sipky doleva/doprava
    if (galleryLightbox && galleryLightbox.classList.contains('show')) {
        if (e.key === "ArrowLeft") changeImage(-1);
        if (e.key === "ArrowRight") changeImage(1);
    }
    if (detailLightbox && detailLightbox.classList.contains('show')) {
        if (e.key === "ArrowLeft") changeDetailImage(-1);
        if (e.key === "ArrowRight") changeDetailImage(1);
    }

    //esc - postupne zavre to, co je nahore (nejvyse)
    if (e.key === 'Escape') {
        if (detailLightbox && detailLightbox.classList.contains('show')) {
            closeDetailLightbox();
            return;
        }
        if (galleryLightbox && galleryLightbox.classList.contains('show')) {
            closeLightbox();
            return;
        }
        
        //pokud jsou to normalni modaly treba login nebo edit
        const activeModals = document.querySelectorAll('.modal-backdrop.show');
        if (activeModals.length > 0) {
            const topModal = activeModals[activeModals.length - 1]; //vezme ten posledni (vrchni)
            closeModal(topModal.id);
        }
    }
});


//vsechny klikaci veci najednou
//jeden listener je lepsi nez sto
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', function (e) {

        //1 kloknuti vedle z modalu pro zavreni
        if (e.target.classList.contains('modal-backdrop')) {
            closeModal(e.target.id);
            return;
        }
        if (e.target.id === 'detailLightbox') {
            closeDetailLightbox();
            return;
        }
        if (e.target.id === 'lightbox') {
            closeLightbox();
            return;
        }

        //2 otevirani modalu pres data atributy
        const modalTrigger = e.target.closest('[data-modal-target]');
        if (modalTrigger) {
            e.preventDefault();
            openModal(modalTrigger.getAttribute('data-modal-target'));
        }

        //3 karta s detailem prispevku (ignoruje kliknuti na tlacitka uvnitr)
        const postCard = e.target.closest('.js-open-detail');
        if (postCard && !e.target.closest('.post-actions-overlay') && !e.target.closest('.post-more-btn')) {
            openDetailModal(JSON.parse(postCard.getAttribute('data-post')));
        }

        //4 edit tlacitka u obsahu
        const editNewsBtn = e.target.closest('.js-edit-news');
        if (editNewsBtn) openEditNewsModal(JSON.parse(editNewsBtn.getAttribute('data-post')));

        const editPostBtn = e.target.closest('.js-edit-post');
        if (editPostBtn) openEditPostModal(JSON.parse(editPostBtn.getAttribute('data-post')));

        const editMemberBtn = e.target.closest('.js-edit-member');
        if (editMemberBtn) openEditMemberModal(JSON.parse(editMemberBtn.getAttribute('data-member')));

        const memberCard = e.target.closest('.js-open-member-detail');
        if (memberCard && !e.target.closest('.member-actions')) {
            openMemberDetailModal(JSON.parse(memberCard.getAttribute('data-member')));
        }

        //5 univerzalni modal pro mazani
        const deleteTrigger = e.target.closest('.js-unified-delete');
        if (deleteTrigger) {
            e.preventDefault();
            e.stopPropagation();

            const title = deleteTrigger.getAttribute('data-title');
            const msg = deleteTrigger.getAttribute('data-msg');
            const url = deleteTrigger.getAttribute('data-url');
            const targetForm = deleteTrigger.getAttribute('data-target-form');
            const returnModal = deleteTrigger.getAttribute('data-return-modal');

            document.getElementById('unifiedDeleteTitle').innerText = title;
            document.getElementById('unifiedDeleteText').innerText = msg;

            const linkBtn = document.getElementById('unifiedDeleteLink');
            const formBtn = document.getElementById('unifiedDeleteFormBtn');
            const cancelBtn = document.getElementById('unifiedDeleteCancelBtn');
            const modal = document.getElementById('unifiedDeleteModal');

            //ulozeni id pro navrat
            modal.dataset.returnModal = returnModal || '';
            if (returnModal) closeModal(returnModal);

            cancelBtn.onclick = () => closeModal('unifiedDeleteModal');

            if (url) {
                //mazani pres link
                linkBtn.href = url;
                linkBtn.style.display = 'inline-block';
                formBtn.style.display = 'none';
            } else if (targetForm) {

                //pro hromadny mazani fotek napr:
                linkBtn.style.display = 'none';
                formBtn.style.display = 'inline-block';
                
                formBtn.onclick = () => {

                    //specialni hack pro form s mazanim fotek z galeroe
                    if (targetForm === 'delete-form') {
                        if (document.querySelectorAll('.delete-checkbox:checked').length === 0) {
                            alert('Musíš vybrat aspoň jednu fotku.');
                            closeModal('unifiedDeleteModal');
                            return;
                        }
                    }
                    document.getElementById(targetForm).submit();
                };
            }
            openModal('unifiedDeleteModal');
        }

        //6 AJAX: popici nacitani fotek bze loadingu
        const galleryLink = e.target.closest('#fotogalerie .year-link, #fotogalerie .cat-link');
        if (galleryLink) {
            e.preventDefault();
            const url = galleryLink.href;

            const gridContainer = document.querySelector('#fotogalerie .photo-grid');
            if (gridContainer) {
                gridContainer.innerHTML = '<p class="empty-msg" style="text-align:center;">Načítám fotky...</p>';
            }

            //fetch API stahne stranku na pozadi
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newYearFilter = doc.querySelector('#fotogalerie .year-filter');
                    const newCategoryFilter = doc.querySelector('#fotogalerie .category-filter');
                    const newPhotoGrid = doc.querySelector('#fotogalerie .photo-grid');
                    const currentGallery = document.getElementById('fotogalerie');

                    if (newYearFilter) {
                        currentGallery.querySelector('.year-filter').replaceWith(newYearFilter);
                    }

                    //zmena filtru
                    const currentCategoryFilter = currentGallery.querySelector('.category-filter');
                    if (newCategoryFilter) {
                        if (currentCategoryFilter) {
                            currentCategoryFilter.replaceWith(newCategoryFilter);
                        } else {
                            currentGallery.querySelector('.year-filter').insertAdjacentElement('afterend', newCategoryFilter);
                        }
                    } else if (currentCategoryFilter) {
                        currentCategoryFilter.remove();
                    }

                    if (newPhotoGrid && gridContainer) {
                        gridContainer.replaceWith(newPhotoGrid);
                    }

                    //zmena url
                    window.history.pushState({path: url}, '', url);
                })
                //kdz se nmeco posere
                .catch(error => {
                    console.error('chyba pri nacitani galerie na pozadíi:', error);
                    window.location.href = url;
                });
        }
    });
});

//pomocne funkce

//ochrana proti XSS
function escapeHtml(t) { 
    return t.replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m])); 
}
//prevod novych radku na <br>
function nl2br(str) { 
    return str.replace(/\n/g, '<br>'); 
}

//filtr akci podle roku
function filterAkceByRok(rok) {
    const url = new URL(window.location.href);
    //reset na stranku 1
    url.searchParams.delete('page_akce');
    if (rok && rok !== '0') {
        url.searchParams.set('rok_akce', rok);
    } else {
        url.searchParams.delete('rok_akce');
    }
    //zachovani aktivni tab
    if (!url.searchParams.has('url') && !url.pathname.includes('akce')) {
        url.pathname = '/akce';
    }
    window.location.href = url.toString();
}

//nastaveni pri nacteni stranky
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    //jestli probihalo upravovani uzivatele -> settings modal zustane otevrneny
    if ((urlParams.has('success') || urlParams.has('error')) && 
        (urlParams.get('success')?.includes('user') || urlParams.get('error')?.includes('user'))) {
        openModal('settingsModal');
    }

    //uvereni detailu
    if (typeof ROUTE_ITEM_ID !== 'undefined' && ROUTE_ITEM_ID !== null) {
        const postCard = document.querySelector('.post-card[data-id="' + ROUTE_ITEM_ID + '"]');
        if (postCard) {
            const postData = JSON.parse(postCard.getAttribute('data-post'));
            openDetailModal(postData);
        }
    }
}

//taky AJAX
window.addEventListener('popstate', function() {
    if(window.location.pathname.includes('fotogalerie')) {
        window.location.reload(); 
    } else {
        const detailModal = document.getElementById('detailModal');
        if (detailModal && detailModal.classList.contains('show')) {
            detailModal.classList.remove('show');
            detailModal.setAttribute('aria-hidden', 'true');
        }
    }
});

//tlacitko na mazani fotek
document.addEventListener('change', function(e) {
    //jenom kdyz checkbox 
    if (e.target && e.target.classList.contains('delete-checkbox')) {
        
        const deleteBtnGroup = document.getElementById('deleteSelectedBtnGroup');
        const checkedBoxes = document.querySelectorAll('.delete-checkbox:checked').length;
        
        console.log("Kliknuto na checkbox! Počet vybraných fotek: " + checkedBoxes);

        if (deleteBtnGroup) {
            if (checkedBoxes > 0) {
                deleteBtnGroup.style.display = 'block'; //zobrazeni tl
            } else {
                deleteBtnGroup.style.display = 'none';  //skryti tl
            }
        } else {
            console.error("Chyba: Tlačítko (div #deleteSelectedBtnGroup) nebylo na stránce nalezeno.");
        }
    }
});

//skryti tlacitka a pro AJAX
const originalFetch = window.fetch;
window.fetch = function() {
    const deleteBtnGroup = document.getElementById('deleteSelectedBtnGroup');
    if (deleteBtnGroup) deleteBtnGroup.style.display = 'none';
    return originalFetch.apply(this, arguments);
};

//kalendar
document.addEventListener('DOMContentLoaded', () => {

    
    let currentCalDate = new Date();

    function renderCalendar() {
        const year = currentCalDate.getFullYear();
        const month = currentCalDate.getMonth();

        const monthNames = ["Leden", "Únor", "Březen", "Duben", "Květen", "Červen", "Červenec", "Srpen", "Září", "Říjen", "Listopad", "Prosinec"];
        const monthYearEl = document.getElementById('calendarMonthYear');
        if (!monthYearEl) return;
        
        monthYearEl.innerText = `${monthNames[month]} ${year}`;

        const daysContainer = document.getElementById('calendarDays');
        daysContainer.innerHTML = '';

        const firstDay = new Date(year, month, 1).getDay();
        const startDay = firstDay === 0 ? 6 : firstDay - 1; 

        const daysInMonth = new Date(year, month + 1, 0).getDate();

        const today = new Date();
        today.setHours(0,0,0,0); 

        //prazdne dny
        for (let i = 0; i < startDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-day empty';
            daysContainer.appendChild(emptyCell);
        }

        //dny v mesici
        for (let i = 1; i <= daysInMonth; i++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'calendar-day';

            const cellDate = new Date(year, month, i);
            cellDate.setHours(0,0,0,0);

            //dnesek
            if (year === today.getFullYear() && month === today.getMonth() && i === today.getDate()) {
                dayCell.classList.add('today');
            }

            const dayNum = document.createElement('div');
            dayNum.className = 'day-number';
            dayNum.innerText = i;
            dayCell.appendChild(dayNum);

            //prirazeni udalosti
            if (typeof CALENDAR_EVENTS !== 'undefined') {
                CALENDAR_EVENTS.forEach(event => {
                    if (!event.datum) return;

                    const eventStart = new Date(event.datum);
                    eventStart.setHours(0,0,0,0);

                    let eventEnd = new Date(eventStart);
                    if (event.datum_do && event.datum_do.trim() !== '') {
                        eventEnd = new Date(event.datum_do);
                        eventEnd.setHours(0,0,0,0);
                    }

                    if (cellDate >= eventStart && cellDate <= eventEnd) {
                        const eventDiv = document.createElement('div');
                        eventDiv.className = 'calendar-event';
                        eventDiv.innerText = event.title;
                        eventDiv.title = event.title;

                        if (eventEnd < today) {
                            eventDiv.classList.add('past');
                        } else {
                            eventDiv.classList.add('future');
                        }

                        //klik pro kalendar
                        eventDiv.addEventListener('click', (e) => {
                            e.stopPropagation();
                            
                            //nazev
                            document.getElementById('calEventTitle').innerText = event.title;
                            
                            //cz datunm
                            let dateText = eventStart.toLocaleDateString('cs-CZ');
                            if (event.datum_do && event.datum_do.trim() !== '') {
                                dateText += ' – ' + eventEnd.toLocaleDateString('cs-CZ');
                            }
                            document.getElementById('calEventDate').innerText = dateText;
                            
                            //odkaz pro vic info
                            const linkBtn = document.getElementById('calEventLink');
                            if (linkBtn) {
                                const urlSekce = event.typ === 'novinka' ? 'aktuality' : 'akce';
                                linkBtn.href = `/${urlSekce}/${event.id}`;
                            }
                            
                            //dalsi modal
                            openModal('calEventModal');
                        });

                        dayCell.appendChild(eventDiv);
                    }
                });
            }
            daysContainer.appendChild(dayCell);
        }
    }

    //tlacitka - preklikavaniu
    const prevBtn = document.getElementById('prevMonth');
    const nextBtn = document.getElementById('nextMonth');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentCalDate.setMonth(currentCalDate.getMonth() - 1);
            renderCalendar();
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            currentCalDate.setMonth(currentCalDate.getMonth() + 1);
            renderCalendar();
        });
    }

    //spusteni
    renderCalendar();
});